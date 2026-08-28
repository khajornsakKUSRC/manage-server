<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ModSecurityLogService
{
    protected const LOG_PATH = '/var/log/httpd/modsec_audit.log';

    /**
     * How much of the tail of the (potentially huge, ever-growing) audit
     * log to pull per request — enough to comfortably cover "the last 5
     * errors" and a same-day date-range filter without transferring the
     * whole file over SSH on every request.
     */
    protected const TAIL_BYTES = 500_000;

    public function __construct(
        protected GuestSshService $ssh,
    ) {}

    /**
     * Connects to the given VM over SSH, tails the ModSecurity audit log,
     * and returns every parsed transaction that matched at least one
     * ModSecurity rule (i.e. every "error"), newest first. The very first
     * (possibly partial) chunk of the tailed output is discarded, since a
     * transaction cut off mid-section can't be reliably parsed.
     *
     * @return array<int, array{id: string, time: Carbon, source_ip: string, request: ?string, messages: array<int, array{text: string, rule_id: ?string, severity: ?string}>}>
     */
    public function fetch(string $host): array
    {
        $output = $this->ssh->run($host, 'tail -c '.self::TAIL_BYTES.' '.self::LOG_PATH.' 2>&1');

        if (str_contains($output, 'Permission denied') || str_contains($output, 'No such file or directory')) {
            throw new RuntimeException("ไม่พบไฟล์ log ที่ ".self::LOG_PATH." บนเครื่องนี้ หรือไม่มีสิทธิ์อ่าน: ".trim($output));
        }

        return $this->parse($output);
    }

    /**
     * @return array<int, array{id: string, time: Carbon, source_ip: string, request: ?string, messages: array<int, array{text: string, rule_id: ?string, severity: ?string}>}>
     */
    protected function parse(string $raw): array
    {
        // The log uses CRLF line endings, which breaks every "^...$"
        // multiline regex below (the trailing \r sits between the matched
        // text and the \n that "$" anchors to) — normalize to LF first.
        $raw = str_replace("\r\n", "\n", $raw);

        // Transactions are delimited by "--<unique-id>-A--" boundary lines
        // (the standard ModSecurity "serial" audit log format). Splitting
        // consumes the boundary itself, so each resulting block is one
        // transaction's Section A through Z content.
        $blocks = preg_split('/^--.+-A--$/m', $raw) ?: [];

        $transactions = collect(array_slice($blocks, 1))
            ->map(fn (string $block) => $this->parseTransaction($block))
            ->filter()
            ->values();

        return $transactions
            ->sortByDesc(fn (array $transaction) => $transaction['time']->timestamp)
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, time: Carbon, source_ip: string, request: ?string, messages: array<int, array{text: string, rule_id: ?string, severity: ?string}>}|null
     */
    protected function parseTransaction(string $block): ?array
    {
        // Section A's header line: "[21/Aug/2026:10:15:32 +0700] <unique-id> <src-ip> <src-port> <dst-ip> <dst-port>"
        if (! preg_match('/^\[([^\]]+)]\s+(\S+)\s+(\S+)\s+\d+\s+\S+\s+\d+/m', $block, $header)) {
            return null;
        }

        $time = $this->parseTime($header[1]);

        if ($time === null) {
            return null;
        }

        // Section H's "Message:" lines carry the actual rule matches — one
        // per rule that fired. A transaction with none didn't trigger
        // ModSecurity, so it isn't an "error".
        preg_match_all('/^Message:\s*(.+)$/m', $block, $messageLines);

        $messages = collect($messageLines[1] ?? [])
            ->map(fn (string $line) => $this->parseMessage($line))
            ->values()
            ->all();

        if (empty($messages)) {
            return null;
        }

        // Section B's first line is the request line, e.g. "GET /path HTTP/1.1".
        $request = null;

        if (preg_match('/^([A-Z]+)\s+(.+)\s+HTTP\/[\d.]+$/m', $block, $requestMatch)) {
            $request = $requestMatch[1].' '.$requestMatch[2];
        }

        return [
            'id' => $header[2],
            'time' => $time,
            'source_ip' => $header[3],
            'request' => $request,
            'messages' => $messages,
        ];
    }

    protected function parseTime(string $raw): ?Carbon
    {
        // Section A's header carries a fractional-seconds suffix (e.g.
        // ".583086") that "d/M/Y:H:i:s O" can't parse — strip it before
        // handing off to Carbon, since sub-second precision isn't needed.
        $raw = preg_replace('/(:\d{2})\.\d+(\s)/', '$1$2', $raw) ?? $raw;

        try {
            $time = Carbon::createFromFormat('d/M/Y:H:i:s O', $raw);
        } catch (Throwable) {
            return null;
        }

        return $time instanceof Carbon ? $time : null;
    }

    /**
     * @return array{text: string, rule_id: ?string, severity: ?string}
     */
    protected function parseMessage(string $line): array
    {
        $ruleId = preg_match('/\[id "([^"]+)"]/', $line, $m) ? $m[1] : null;
        $severity = preg_match('/\[severity "([^"]+)"]/', $line, $m) ? $m[1] : null;

        return [
            'text' => trim($line),
            'rule_id' => $ruleId,
            'severity' => $severity,
        ];
    }
}
