<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class RadiusLogService
{
    protected const LOG_PATH = '/var/log/radius/radius.log';

    /**
     * How much of the tail of the log to pull for the live view — enough
     * to comfortably find the last 50 auth lines (plus room for
     * filtering) without transferring the whole (600MB+) file over SSH
     * every time.
     */
    protected const TAIL_BYTES = 500_000;

    public const DEFAULT_LIMIT = 5;

    public const ALLOWED_LIMITS = [5, 20, 50, 100];

    protected const CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Export range is capped — the live log alone runs to several hundred
     * MB and ~800k lines/day on this server, so the time span needs a
     * tight ceiling (as well as the row count below) to keep one request
     * from trying to transfer and parse an unbounded amount of data over
     * an interactive SSH shell. A wider range was tried in production and
     * brought the server down, hence the aggressive 1-hour cap.
     */
    public const MAX_EXPORT_MINUTES = 60;

    // Traffic here is heavy enough that even one day can produce
    // hundreds of thousands of lines — capped well below PHP's default
    // memory limit (each parsed row holds a Carbon instance, and the
    // export additionally builds a spreadsheet from them) and well below
    // what's actually practical to browse in Excel anyway.
    public const MAX_EXPORT_ROWS = 20_000;

    public function __construct(
        protected SshSuEscalation $suEscalation,
    ) {}

    /**
     * How many matching lines a filtered search (see buildSearchCommand())
     * pulls back from the server before PHP-side filtering/limiting, e.g.
     * a broad status search like "OK" that could otherwise match a huge
     * fraction of the log.
     */
    protected const MAX_SEARCH_LINES = 20_000;

    /**
     * Connects over SSH to the configured KUWIN Radius server and parses
     * every "Login OK"/"Login incorrect"-style auth line, returning up to
     * the last $limit (default 5) — newest first — optionally narrowed by
     * username, MAC address (the RADIUS "cli" field), client (NAS) name,
     * and/or status (e.g. "OK", "incorrect", "eap"). Each needle matches
     * as a case-insensitive substring, so a partial value still finds it.
     *
     * With no filters, this only tails the live log's recent bytes (fast,
     * for casual browsing). As soon as any filter is set, it searches the
     * *entire* log instead — the live file plus every rotated
     * radius.log-YYYYMMDD[.gz] — since a search that silently only covered
     * the last ~500KB window would miss anything older than a few
     * minutes.
     *
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    public function fetch(?string $username = null, ?string $mac = null, ?string $client = null, ?string $status = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $ssh = $this->connect();

        $hasSearch = $username !== null || $mac !== null || $client !== null || $status !== null;

        $command = $hasSearch
            ? $this->buildSearchCommand($username, $mac, $client, $status)
            : 'tail -c '.self::TAIL_BYTES.' '.self::LOG_PATH;

        $entries = $this->parse($this->runPrivileged($ssh, $command));
        $entries = $this->applyFilters($entries, $username, $mac, $client, $status);

        return array_slice($entries, 0, $limit);
    }

    /**
     * Same as fetch(), but searches every log covering the given
     * (inclusive) date+time range — capped at MAX_EXPORT_MINUTES — instead
     * of just the recent tail: the live radius.log plus any rotated
     * radius.log-YYYYMMDD[.gz] files whose date it touches. The
     * minute-level filtering happens on the server (grep/zgrep) before
     * anything is transferred, since pulling the raw files here first
     * isn't practical at their size; the exact second-level boundary is
     * then applied here in PHP, since grep only matched down to the
     * minute. Results are capped at MAX_EXPORT_ROWS, newest first.
     *
     * Even a 1-hour window can carry more than MAX_EXPORT_ROWS lines on a
     * busy day (observed live: ~20k lines in 30 minutes at peak) — when
     * that happens, $truncated is set true and the returned rows are
     * whichever came first chronologically within the window, not the
     * whole window. $truncated is an explicit by-reference out param
     * (rather than silently dropping data) so the caller can warn the
     * user their export may be incomplete instead of it looking complete.
     *
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    public function fetchRange(Carbon $from, Carbon $to, ?string $username = null, ?string $mac = null, ?string $client = null, ?string $status = null, ?bool &$truncated = null): array
    {
        if ($from->diffInMinutes($to) > self::MAX_EXPORT_MINUTES) {
            throw new RuntimeException('เลือกช่วงเวลาได้ไม่เกิน '.self::MAX_EXPORT_MINUTES.' นาที เพื่อป้องกันไม่ให้ server ล่มจากการดึงข้อมูลจำนวนมากเกินไป');
        }

        $ssh = $this->connect();

        $command = $this->buildRangeCommand($from, $to);
        $raw = $this->runPrivileged($ssh, $command);
        $rawLineCount = $raw === '' ? 0 : count(preg_split('/\r?\n/', trim($raw)) ?: []);

        // head -n MAX_EXPORT_ROWS filled its cap exactly — there could be
        // more matching lines it never got to read, so treat this as a
        // (possibly false-positive, but safe to over-warn on) truncation.
        $truncated = $rawLineCount >= self::MAX_EXPORT_ROWS;

        $entries = $this->parse($raw);
        $entries = array_values(array_filter($entries, fn (array $entry) => $entry['time']->between($from, $to)));
        $entries = $this->applyFilters($entries, $username, $mac, $client, $status);

        return array_slice($entries, 0, self::MAX_EXPORT_ROWS);
    }

    private function connect(): SSH2
    {
        $host = config('services.radius.host');

        // This server is outside the regular VM fleet Smart
        // Detection/ModSecurity SSH into, so it very likely needs its own
        // login — RADIUS_SSH_USERNAME/PASSWORD if set, otherwise falls
        // back to the shared guest_ssh credential in case that happens to
        // work here too.
        $sshUsername = config('services.radius.ssh_username') ?: config('services.guest_ssh.username');
        $sshPassword = config('services.radius.ssh_password') ?: config('services.guest_ssh.password');
        $port = (int) (config('services.radius.ssh_port') ?: config('services.guest_ssh.port', 22));

        if (! $sshUsername || ! $sshPassword) {
            throw new RuntimeException('กรุณาตั้งค่า RADIUS_SSH_USERNAME/PASSWORD (หรือ GUEST_SSH_USERNAME/PASSWORD) ในไฟล์ .env');
        }

        $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);

        try {
            $loggedIn = $ssh->login($sshUsername, $sshPassword);
        } catch (Throwable $e) {
            throw new RuntimeException("ไม่สามารถเชื่อมต่อ SSH ไปยัง {$host} ได้: ".$e->getMessage());
        }

        if (! $loggedIn) {
            throw new RuntimeException("เข้าสู่ระบบ SSH ที่ {$host} ไม่สำเร็จ กรุณาตรวจสอบ username/password: ".$ssh->getLastError());
        }

        return $ssh;
    }

    /**
     * Builds a shell command that greps (or zgreps, for the compressed
     * rotated ones) every radius.log* file for lines whose syslog
     * timestamp falls within [from, to], matched down to the *minute*
     * (not just the day) since the range is capped at MAX_EXPORT_MINUTES.
     *
     * Minute-level matching matters here, not just precision: `head -n
     * MAX_EXPORT_ROWS` below takes whichever matching lines come first in
     * file order (i.e. chronologically earliest), so a day-level pattern
     * on a busy log — where one day alone can hold ~800k lines — would
     * let head fill its cap from the start of the day and cut off before
     * ever reaching a requested window later that day, silently returning
     * nothing. Matching only the actual requested minutes keeps what heads
     * caps proportionate to the requested window instead of the whole day.
     */
    private function buildRangeCommand(Carbon $from, Carbon $to): string
    {
        $minutes = [];
        $cursor = $from->copy()->startOfMinute();
        $end = $to->copy()->startOfMinute();

        while ($cursor->lte($end)) {
            // Syslog's "%b %e" format space-pads single-digit days (e.g.
            // "Aug  9"), which [[:space:]]+ already matches either way.
            // The trailing ":" anchors to the seconds field so e.g. "14:5"
            // can't accidentally match inside "14:50".
            $minutes[] = preg_quote($cursor->format('M'), '/').'[[:space:]]+'.(int) $cursor->format('j').'[[:space:]]+'.$cursor->format('H:i').':';
            $cursor->addMinute();
        }

        $pattern = escapeshellarg('^('.implode('|', $minutes).')');
        $glob = self::LOG_PATH.'*';

        return 'for f in '.$glob.'; do case "$f" in *.gz) zgrep -h -E '.$pattern.' "$f" ;; *) grep -h -E '.$pattern.' "$f" ;; esac; done | head -n '.self::MAX_EXPORT_ROWS;
    }

    /**
     * Builds a shell command that greps (or zgreps, for the compressed
     * rotated ones) every radius.log* file for any line containing at
     * least one of the given search terms — a cheap OR superset done on
     * the server so only plausibly-relevant lines are transferred; the
     * exact per-field AND matching happens afterwards in applyFilters().
     * Capped at MAX_SEARCH_LINES lines so a broad, unselective term (e.g.
     * a status search for "OK") can't try to stream a huge share of the
     * log back.
     */
    private function buildSearchCommand(?string $username, ?string $mac, ?string $client, ?string $status): string
    {
        $terms = array_values(array_filter(
            [$username, $mac, $client, $status],
            fn (?string $term) => $term !== null && $term !== '',
        ));

        $needleFlags = implode(' ', array_map(
            fn (string $term) => '-e '.escapeshellarg($term),
            $terms,
        ));

        $glob = self::LOG_PATH.'*';

        return 'for f in '.$glob.'; do case "$f" in *.gz) zgrep -h -i -F '.$needleFlags.' "$f" ;; *) grep -h -i -F '.$needleFlags.' "$f" ;; esac; done | head -n '.self::MAX_SEARCH_LINES;
    }

    /**
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    private function applyFilters(array $entries, ?string $username, ?string $mac, ?string $client, ?string $status): array
    {
        if ($username === null && $mac === null && $client === null && $status === null) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            fn (array $entry) => $this->matches($entry, $username, $mac, $client, $status),
        ));
    }

    private function matches(array $entry, ?string $username, ?string $mac, ?string $client, ?string $status): bool
    {
        if ($username !== null && ! $this->contains($entry['username'], $username)) {
            return false;
        }

        if ($mac !== null && ! $this->contains($entry['mac'], $mac)) {
            return false;
        }

        if ($client !== null && ! $this->contains($entry['client'], $client)) {
            return false;
        }

        if ($status !== null && ! $this->contains($entry['status'], $status)) {
            return false;
        }

        return true;
    }

    private function contains(?string $haystack, string $needle): bool
    {
        return $haystack !== null && str_contains(strtolower($haystack), strtolower($needle));
    }

    /**
     * The logged-in user can't read radius.log directly (confirmed:
     * FreeRADIUS logs here are root-only) — tries the cheap direct read
     * first anyway in case permissions ever change, and only falls back
     * to the slower interactive "su -" escalation when that's denied.
     */
    private function runPrivileged(SSH2 $ssh, string $command): string
    {
        // A full-log search (grep/zgrep across every rotated file) can run
        // well past the connect timeout — give the direct-exec path the
        // same generous headroom the su-fallback path below already gets.
        $ssh->setTimeout(120);

        $direct = (string) $ssh->exec($command.' 2>&1');

        // Commands piped through e.g. `| head` (see buildRangeCommand())
        // report head's exit status, not grep's — head succeeds even
        // reading nothing from a permission-denied grep, so the exit code
        // alone can't be trusted to detect that case; a literal
        // "Permission denied" in the output is a reliable second signal.
        if ($ssh->getExitStatus() === 0 && ! str_contains($direct, 'Permission denied')) {
            return $direct;
        }

        $suPassword = config('services.radius.su_password');

        if (! $suPassword) {
            throw new RuntimeException(
                'ไม่มีสิทธิ์อ่านไฟล์ log โดยตรง (ต้อง su เป็น root) แต่ยังไม่ได้ตั้งค่า RADIUS_SU_PASSWORD ในไฟล์ .env: '
                .trim((string) $direct),
            );
        }

        // Export commands over a large date range can take a while (grep
        // across a 600MB+ file), so this gets a longer exec timeout than
        // SshSuEscalation's default.
        return $this->suEscalation->runAsRoot($ssh, $suPassword, $command, self::CONNECT_TIMEOUT_SECONDS, 120);
    }

    /**
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    protected function parse(string $raw): array
    {
        $lines = preg_split('/\r?\n/', trim($raw)) ?: [];

        $entries = collect($lines)
            ->map(fn (string $line) => $this->parseLine($line))
            ->filter()
            ->values()
            ->all();

        usort($entries, fn (array $a, array $b) => $b['time']->timestamp <=> $a['time']->timestamp);

        return $entries;
    }

    /**
     * Parses one FreeRADIUS log line, e.g.:
     * "Aug 28 11:35:32 src-radius1 radiusd[851732]: (94853979) Login OK: [b6630302052/<via Auth-Type = eap>] (from client wlc-c9800CL port 15914 cli 6e296c364562 via TLS tunnel)"
     * Lines that don't match this shape (startup/rotation/debug lines,
     * mainly) are skipped rather than shown as garbled entries.
     *
     * @return array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}|null
     */
    protected function parseLine(string $line): ?array
    {
        $pattern = '/^(?<ts>[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+\S+\s+radiusd\[\d+\]:\s+\((?<reqid>\d+)\)\s+(?<status>[^:]+):\s+\[(?<user>[^\]]*)\]'
            // Some lines add a trailing qualifier before the closing paren,
            // e.g. "cli 02f13dce1950 via TLS tunnel)" — .*? absorbs that.
            .'(?:\s*\(from client (?<client>\S+)(?:\s+port\s+(?<port>\d+))?(?:\s+cli\s+(?<cli>\S+))?.*?\))?/';

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        $time = $this->parseTime($m['ts']);

        if ($time === null) {
            return null;
        }

        // The bracketed user field sometimes carries the auth method too,
        // e.g. "b6830106656/<via Auth-Type = eap>" (observed live — the
        // separator's exact character order isn't consistent, so match
        // any run of "/"/"<" rather than a literal "</").
        $username = trim($m['user']);
        $authType = null;

        if (preg_match('/^(.*?)[\/<]+via\s+Auth-Type\s*=\s*([^>]+)>?$/', $username, $um)) {
            $username = trim($um[1]);
            $authType = trim($um[2]);
        }

        $status = trim($m['status']);

        return [
            'time' => $time,
            'request_id' => $m['reqid'],
            'status' => $status,
            'status_ok' => stripos($status, 'ok') !== false,
            'username' => $username !== '' ? $username : null,
            'auth_type' => $authType,
            'client' => (! empty($m['client'])) ? $m['client'] : null,
            'port' => (! empty($m['port'])) ? $m['port'] : null,
            'mac' => (! empty($m['cli'])) ? $m['cli'] : null,
            'raw' => $line,
        ];
    }

    /**
     * Syslog timestamps carry no year — assumes the current year, rolling
     * back one if that would put the entry in the future (e.g. reading a
     * December line early in the following January).
     */
    protected function parseTime(string $raw): ?Carbon
    {
        try {
            $time = Carbon::createFromFormat('M j H:i:s', $raw)->year(now()->year);
        } catch (Throwable) {
            return null;
        }

        if (! $time instanceof Carbon) {
            return null;
        }

        if ($time->isFuture()) {
            $time = $time->subYear();
        }

        return $time;
    }
}
