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

    protected const LIMIT = 50;

    protected const CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Export range is capped — the live log alone runs to several hundred
     * MB and ~800k lines/day on this server, so both the date span and the
     * row count need a ceiling to keep one request from trying to
     * transfer and parse an unbounded amount of data over an interactive
     * SSH shell.
     */
    public const MAX_EXPORT_DAYS = 7;

    // Traffic here is heavy enough that even one day can produce
    // hundreds of thousands of lines — capped well below PHP's default
    // memory limit (each parsed row holds a Carbon instance, and the
    // export additionally builds a spreadsheet from them) and well below
    // what's actually practical to browse in Excel anyway.
    public const MAX_EXPORT_ROWS = 20_000;

    /**
     * Connects over SSH to the configured KUWIN Radius server, tails its
     * log, parses every "Login OK"/"Login incorrect"-style auth line, and
     * returns up to the last 50 — newest first — optionally narrowed by
     * username, MAC address (the RADIUS "cli" field), and/or client (NAS)
     * name. Each needle matches as a case-insensitive substring, so a
     * partial username or MAC still finds it.
     *
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    public function fetch(?string $username = null, ?string $mac = null, ?string $client = null): array
    {
        $ssh = $this->connect();

        $command = 'tail -c '.self::TAIL_BYTES.' '.self::LOG_PATH;
        $entries = $this->parse($this->runPrivileged($ssh, $command));
        $entries = $this->applyFilters($entries, $username, $mac, $client);

        return array_slice($entries, 0, self::LIMIT);
    }

    /**
     * Same as fetch(), but searches every log covering the given
     * (inclusive) date range — the live radius.log plus any rotated
     * radius.log-YYYYMMDD[.gz] files whose date it doesn't cover — instead
     * of just the recent tail. The date filtering happens on the server
     * (grep/zgrep) before anything is transferred, since pulling the raw
     * files here first isn't practical at their size. Results are capped
     * at MAX_EXPORT_ROWS, newest first.
     *
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    public function fetchRange(Carbon $from, Carbon $to, ?string $username = null, ?string $mac = null, ?string $client = null): array
    {
        if ($from->diffInDays($to) >= self::MAX_EXPORT_DAYS) {
            throw new RuntimeException('เลือกช่วงวันที่ได้ไม่เกิน '.self::MAX_EXPORT_DAYS.' วัน');
        }

        $ssh = $this->connect();

        $command = $this->buildRangeCommand($from, $to);
        $entries = $this->parse($this->runPrivileged($ssh, $command));
        $entries = $this->applyFilters($entries, $username, $mac, $client);

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
     * rotated ones) every radius.log* file for lines whose syslog date
     * falls within [from, to], capped at MAX_EXPORT_ROWS lines so a wide
     * range can't try to stream millions of lines back.
     */
    private function buildRangeCommand(Carbon $from, Carbon $to): string
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            // Syslog's "%b %e" format space-pads single-digit days (e.g.
            // "Aug  9"), which [[:space:]]+ already matches either way.
            $days[] = preg_quote($cursor->format('M'), '/').'[[:space:]]+'.(int) $cursor->format('j');
            $cursor->addDay();
        }

        $pattern = escapeshellarg('^('.implode('|', $days).')[[:space:]]');
        $glob = self::LOG_PATH.'*';

        return 'for f in '.$glob.'; do case "$f" in *.gz) zgrep -h -E '.$pattern.' "$f" ;; *) grep -h -E '.$pattern.' "$f" ;; esac; done | head -n '.self::MAX_EXPORT_ROWS;
    }

    /**
     * @return array<int, array{time: Carbon, request_id: string, status: string, status_ok: bool, username: ?string, auth_type: ?string, client: ?string, port: ?string, mac: ?string, raw: string}>
     */
    private function applyFilters(array $entries, ?string $username, ?string $mac, ?string $client): array
    {
        if ($username === null && $mac === null && $client === null) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            fn (array $entry) => $this->matches($entry, $username, $mac, $client),
        ));
    }

    private function matches(array $entry, ?string $username, ?string $mac, ?string $client): bool
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

        return $this->runAsRoot($ssh, $suPassword, $command);
    }

    /**
     * Escalates to root over an interactive shell — phpseclib's exec()
     * only runs one-off non-interactive commands, so "su" (which needs a
     * password typed back at its own prompt) has to go through the
     * shell's write()/read() instead, the same way a human would type
     * `su`, wait for "Password:", then type the password.
     */
    private function runAsRoot(SSH2 $ssh, string $suPassword, string $command): string
    {
        $ssh->setTimeout(self::CONNECT_TIMEOUT_SECONDS);

        // Opens the interactive shell channel (lazily, on first read/write)
        // and waits out the login banner/MOTD down to the initial prompt.
        $ssh->read('/[$#]\s*$/', SSH2::READ_REGEX);

        $ssh->write("su -\n");
        $passwordPrompt = $ssh->read('/[Pp]assword:\s*$/', SSH2::READ_REGEX);

        if ($ssh->isTimeout() || ! preg_match('/[Pp]assword:/', $passwordPrompt)) {
            throw new RuntimeException('ไม่สามารถ su เป็น root ได้ (ไม่พบ prompt สำหรับใส่ password)');
        }

        $ssh->write($suPassword."\n");
        $rootPrompt = $ssh->read('/[#$]\s*$/', SSH2::READ_REGEX);

        if ($ssh->isTimeout() || stripos($rootPrompt, 'incorrect') !== false || stripos($rootPrompt, 'failure') !== false) {
            throw new RuntimeException('su เป็น root ไม่สำเร็จ กรุณาตรวจสอบ RADIUS_SU_PASSWORD ในไฟล์ .env');
        }

        // A random marker delimits the command's output from the shell's
        // next prompt, since an interactive shell has no clean EOF signal
        // the way exec() does. The shell echoes back whatever we typed
        // (including the literal "echo $marker" text) before the command
        // even runs, so the *first* time the marker appears in the stream
        // is just that echo — discard it and wait for the marker a second
        // time, which is the real `echo` output after the command finishes.
        // Export commands over a large date range can take a while (grep
        // across a 600MB+ file), so this uses a longer timeout than the
        // earlier prompt exchanges.
        $marker = 'RADIUSLOGEND_'.bin2hex(random_bytes(4));
        $ssh->setTimeout(120);
        $ssh->write($command.'; echo '.$marker."\n");
        $ssh->read('/'.$marker.'/', SSH2::READ_REGEX);
        $result = (string) $ssh->read('/'.$marker.'/', SSH2::READ_REGEX);

        // Strip everything from the marker onward (its own echo, plus the
        // shell's next prompt), and any terminal control sequences (e.g.
        // bracketed-paste mode toggles) bash's readline emits around the
        // prompt boundary.
        $result = preg_replace('/'.$marker.'.*$/s', '', $result) ?? $result;
        $result = preg_replace('/\x1B\[[0-9;?]*[a-zA-Z]/', '', $result) ?? $result;

        return trim($result);
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
