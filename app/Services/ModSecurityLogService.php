<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use phpseclib3\Net\SSH2;
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

    protected const CONNECT_TIMEOUT_SECONDS = 10;

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
        $username = config('services.guest_ssh.username');
        $password = config('services.guest_ssh.password');
        $port = (int) config('services.guest_ssh.port', 22);

        if (! $username || ! $password) {
            throw new RuntimeException('กรุณาตั้งค่า GUEST_SSH_USERNAME และ GUEST_SSH_PASSWORD ในไฟล์ .env');
        }

        $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);

        try {
            $loggedIn = $ssh->login($username, $password);
        } catch (Throwable $e) {
            throw new RuntimeException("ไม่สามารถเชื่อมต่อ SSH ไปยัง {$host} ได้: ".$e->getMessage());
        }

        if (! $loggedIn) {
            throw new RuntimeException("เข้าสู่ระบบ SSH ที่ {$host} ไม่สำเร็จ กรุณาตรวจสอบ username/password: ".$ssh->getLastError());
        }

        $output = $ssh->exec('tail -c '.self::TAIL_BYTES.' '.self::LOG_PATH.' 2>&1');

        if ($ssh->getExitStatus() !== 0) {
            throw new RuntimeException("ไม่พบไฟล์ log ที่ ".self::LOG_PATH." บนเครื่องนี้ หรือไม่มีสิทธิ์อ่าน: ".trim((string) $output));
        }

        return $this->parse((string) $output);
    }

    /**
     * @return array<int, array{id: string, time: Carbon, source_ip: string, request: ?string, messages: array<int, array{text: string, rule_id: ?string, severity: ?string}>}>
     */
    protected function parse(string $raw): array
    {
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
