<?php

namespace App\Services;

use App\Models\SmartDetectionFinding;
use App\Models\SmartDetectionState;
use App\Models\Vm;
use Illuminate\Support\Collection;

class SmartDetectionService
{
    /**
     * systemd unit names checked for Service Failure — covers the common
     * package names across Debian/Ubuntu (apache2, mysql) and RHEL/CentOS
     * (httpd, mysqld/mariadb) for the same services, since a fleet is
     * rarely one distro. A name systemctl doesn't recognize just reports
     * "unknown"/"inactive" and is silently never flagged (see
     * detectServiceFailures) — it's not treated as a failure.
     */
    protected const MONITORED_SERVICES = [
        'apache2', 'httpd', 'mysql', 'mysqld', 'mariadb', 'postfix', 'nginx', 'sshd',
    ];

    /**
     * Failed login attempts from the same source IP, within one scan
     * interval, before it's flagged as a possible brute-force attempt.
     */
    protected const BRUTE_FORCE_THRESHOLD = 5;

    /**
     * Process name/command-line patterns strongly associated with known
     * cryptominer/malware families — a real (if narrow) signal, unlike the
     * "new to this VM" baselines used for ports/processes/cron below.
     *
     * Each keyword has one character wrapped in a single-char bracket
     * class (`x[m]rig` still matches literal "xmrig") — the standard
     * `ps aux | grep '[s]shd'` trick, needed here because this exact
     * string is embedded in the very shell script being grepped: without
     * it, both the grep process's own argv and the outer `bash -c`
     * process's argv (which contains the whole script, including this
     * pattern) literally contain the plain keywords and self-match,
     * flagging the detection scan itself as malware on every run.
     */
    protected const SUSPICIOUS_PROCESS_PATTERN = 'x[m]rig|k[i]nsing|kdevtmpf[s]i|stratum\+tc[p]|cryptonigh[t]|min[e]rd';

    public function __construct(
        protected GuestSshService $ssh,
    ) {}

    /**
     * Runs every detection category against one VM in a single SSH
     * session (one combined shell script, not five separate connections)
     * and records/updates findings for whatever it turns up. Returns one
     * entry per finding touched this scan, each flagging whether it's
     * newly created or reopened after being resolved — the signal a
     * caller should alert on — versus just a recurring already-open one.
     *
     * @return Collection<int, array{finding: SmartDetectionFinding, is_new_or_reopened: bool}>
     */
    public function scanVm(Vm $vm): Collection
    {
        $output = $this->ssh->run($vm->ip, $this->buildScript());
        $sections = $this->splitSections($output);

        $specs = collect([
            ...$this->detectServiceFailures($vm, $sections),
            ...$this->detectPortsAndServices($vm, $sections),
            ...$this->detectProcesses($vm, $sections),
            ...$this->detectBruteForce($vm, $sections),
            ...$this->detectMalware($vm, $sections),
        ]);

        return $specs->map(fn (array $spec) => $this->recordFinding($vm, $spec));
    }

    protected function buildScript(): string
    {
        $services = implode(' ', self::MONITORED_SERVICES);
        $suspiciousPattern = self::SUSPICIOUS_PROCESS_PATTERN;

        return <<<SCRIPT
            echo '@@SERVICES@@'
            systemctl is-active {$services} 2>&1
            echo '@@PORTS@@'
            (ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null)
            echo '@@PROCESSES@@'
            ps -eo pid,ppid,comm --no-headers 2>/dev/null | awk '$2 != 2 {print $3}' | sort -u
            echo '@@AUTHLOG@@'
            (tail -n 2000 /var/log/auth.log 2>/dev/null || tail -n 2000 /var/log/secure 2>/dev/null)
            echo '@@WORLDWRITABLE@@'
            find /etc /usr/bin /usr/sbin /bin /sbin -xdev -maxdepth 2 -perm -0002 -type f 2>/dev/null
            echo '@@SUSPICIOUSPROC@@'
            ps -eo comm,args --no-headers 2>/dev/null | grep -Ei '{$suspiciousPattern}'
            echo '@@CRON@@'
            (crontab -l -u root 2>/dev/null; cat /etc/cron.d/* 2>/dev/null) | grep -Ev '^#|^[[:space:]]*$'
            echo '@@DONE@@'
            SCRIPT;
    }

    /**
     * @return array<string, string>
     */
    protected function splitSections(string $output): array
    {
        $parts = preg_split('/@@([A-Z]+)@@\r?\n/', $output, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [];

        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $sections[$parts[$i]] = trim($parts[$i + 1]);
        }

        return $sections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function detectServiceFailures(Vm $vm, array $sections): array
    {
        $lines = preg_split('/\r?\n/', $sections['SERVICES'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $current = [];

        foreach (array_values(self::MONITORED_SERVICES) as $i => $service) {
            $current[$service] = trim($lines[$i] ?? 'unknown');
        }

        $previous = $this->getState($vm, 'service_failure')['services'] ?? [];

        $findings = [];

        foreach ($current as $service => $status) {
            $wasActive = ($previous[$service] ?? null) === 'active';

            if ($status === 'failed' || ($wasActive && $status !== 'active')) {
                $findings[] = [
                    'category' => 'service_failure',
                    'fingerprint' => "service:{$service}",
                    'severity' => 'critical',
                    'title' => "Service '{$service}' is down",
                    'detail' => "systemctl reports '{$service}' as '{$status}'"
                        .($wasActive ? ' (was active as of the previous scan)' : '').'.',
                ];
            } elseif ($status === 'active') {
                // Recovered (or was already fine) — close any open finding.
                SmartDetectionFinding::where('vm_id', $vm->id)
                    ->where('category', 'service_failure')
                    ->where('fingerprint', "service:{$service}")
                    ->where('status', '!=', SmartDetectionFinding::STATUS_RESOLVED)
                    ->update(['status' => SmartDetectionFinding::STATUS_RESOLVED, 'resolved_at' => now()]);
            }
        }

        $this->saveState($vm, 'service_failure', ['services' => $current]);

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function detectPortsAndServices(Vm $vm, array $sections): array
    {
        $current = $this->parseListeningPorts($sections['PORTS'] ?? '');
        $known = $this->getState($vm, 'port_service')['known_ports'] ?? null;

        $findings = [];

        // null means this VM has never been scanned before — that first
        // scan only establishes the baseline; everything already listening
        // is "normal", not "new".
        if ($known !== null) {
            foreach (array_diff($current, $known) as $portProc) {
                [$port, $proc] = array_pad(explode(':', $portProc, 2), 2, 'unknown');

                $findings[] = [
                    'category' => 'port_service',
                    'fingerprint' => "port:{$portProc}",
                    'severity' => 'warning',
                    'title' => "New listening port {$port} ({$proc})",
                    'detail' => "Port {$port} is now listening (process: {$proc}) — not seen in any previous scan.",
                ];
            }
        }

        $this->saveState($vm, 'port_service', [
            'known_ports' => array_values(array_unique(array_merge($known ?? [], $current))),
        ]);

        return $findings;
    }

    /**
     * @return array<int, string> "port:process" pairs
     */
    protected function parseListeningPorts(string $raw): array
    {
        $ports = [];

        foreach (preg_split('/\r?\n/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            if (! str_contains($line, 'LISTEN')) {
                continue;
            }

            if (preg_match('/:(\d+)\s.*?\(\("([^"]+)"/', $line, $m)) {
                $ports["{$m[1]}:{$m[2]}"] = true;
            } elseif (preg_match('#:(\d+)\s+[\d.:*]+\s+LISTEN\s+\d+/(\S+)#', $line, $m)) {
                $ports["{$m[1]}:{$m[2]}"] = true;
            } elseif (preg_match('/[\d.:*\[\]]+:(\d+)\s/', $line, $m)) {
                $ports["{$m[1]}:unknown"] = true;
            }
        }

        return array_keys($ports);
    }

    /**
     * The PROCESSES section (built by buildScript()) already excludes
     * kernel threads (anything parented by kthreadd, PID 2) — kworker,
     * ksoftirqd, rcu_ and friends get freshly-numbered, never-repeating
     * names on every scan, so treating them as "new to this VM" would
     * flag nearly every kernel thread as a new process on every run.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function detectProcesses(Vm $vm, array $sections): array
    {
        $current = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/\r?\n/', $sections['PROCESSES'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ))));

        $known = $this->getState($vm, 'process')['known_processes'] ?? null;

        $findings = [];

        if ($known !== null) {
            foreach (array_diff($current, $known) as $process) {
                $findings[] = [
                    'category' => 'process',
                    'fingerprint' => "process:{$process}",
                    'severity' => 'info',
                    'title' => "New process observed: {$process}",
                    'detail' => "'{$process}' has not been seen running on this VM in any previous scan.",
                ];
            }
        }

        $this->saveState($vm, 'process', [
            'known_processes' => array_values(array_unique(array_merge($known ?? [], $current))),
        ]);

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function detectBruteForce(Vm $vm, array $sections): array
    {
        $lines = preg_split('/\r?\n/', $sections['AUTHLOG'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $watermark = $this->getState($vm, 'brute_force')['last_line'] ?? null;

        $findings = [];

        if ($watermark !== null) {
            $pos = array_search($watermark, $lines, true);
            // If the watermark line can't be found (log rotated since the
            // last scan), fall back to the whole fetched tail — better to
            // over-count once after a rotation than silently miss an
            // ongoing attack.
            $newLines = $pos !== false ? array_slice($lines, $pos + 1) : $lines;

            $counts = [];

            foreach ($newLines as $line) {
                if (preg_match('/Failed password.*?from\s+(\d{1,3}(?:\.\d{1,3}){3})/', $line, $m)) {
                    $counts[$m[1]] = ($counts[$m[1]] ?? 0) + 1;
                }
            }

            foreach ($counts as $ip => $count) {
                if ($count >= self::BRUTE_FORCE_THRESHOLD) {
                    $findings[] = [
                        'category' => 'brute_force',
                        'fingerprint' => "bruteforce:{$ip}",
                        'severity' => 'critical',
                        'title' => "Possible brute-force from {$ip}",
                        'detail' => "{$count} failed login attempts from {$ip} since the last scan (threshold: ".self::BRUTE_FORCE_THRESHOLD.').',
                    ];
                }
            }
        }

        if (! empty($lines)) {
            $this->saveState($vm, 'brute_force', ['last_line' => end($lines)]);
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function detectMalware(Vm $vm, array $sections): array
    {
        $findings = [];

        // Known-bad process patterns always flag — no baseline needed.
        foreach (preg_split('/\r?\n/', $sections['SUSPICIOUSPROC'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim($line);

            $findings[] = [
                'category' => 'malware',
                'fingerprint' => 'suspicious_process:'.md5($line),
                'severity' => 'critical',
                'title' => 'Suspicious process matched a known malware pattern',
                'detail' => $line,
            ];
        }

        $state = $this->getState($vm, 'malware');

        $currentWritable = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/\r?\n/', $sections['WORLDWRITABLE'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ))));
        $knownWritable = $state['known_writable'] ?? null;

        if ($knownWritable !== null) {
            foreach (array_diff($currentWritable, $knownWritable) as $path) {
                $findings[] = [
                    'category' => 'malware',
                    'fingerprint' => 'writable:'.$path,
                    'severity' => 'warning',
                    'title' => 'New world-writable system file',
                    'detail' => "{$path} is world-writable — a common persistence/tampering technique.",
                ];
            }
        }

        $currentCron = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/\r?\n/', $sections['CRON'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ))));
        $knownCron = $state['known_cron'] ?? null;

        if ($knownCron !== null) {
            foreach (array_diff($currentCron, $knownCron) as $entry) {
                $findings[] = [
                    'category' => 'malware',
                    'fingerprint' => 'cron:'.md5($entry),
                    'severity' => 'warning',
                    'title' => 'New scheduled task (cron) entry',
                    'detail' => $entry,
                ];
            }
        }

        $this->saveState($vm, 'malware', [
            'known_writable' => array_values(array_unique(array_merge($knownWritable ?? [], $currentWritable))),
            'known_cron' => array_values(array_unique(array_merge($knownCron ?? [], $currentCron))),
        ]);

        return $findings;
    }

    protected function getState(Vm $vm, string $category): array
    {
        return SmartDetectionState::where('vm_id', $vm->id)
            ->where('category', $category)
            ->value('state') ?? [];
    }

    protected function saveState(Vm $vm, string $category, array $state): void
    {
        SmartDetectionState::updateOrCreate(
            ['vm_id' => $vm->id, 'category' => $category],
            ['state' => $state],
        );
    }

    /**
     * @return array{finding: SmartDetectionFinding, is_new_or_reopened: bool}
     */
    protected function recordFinding(Vm $vm, array $spec): array
    {
        $now = now();

        $finding = SmartDetectionFinding::firstOrNew([
            'vm_id' => $vm->id,
            'category' => $spec['category'],
            'fingerprint' => $spec['fingerprint'],
        ]);

        // Notification-worthy is broader than "brand new row": a finding
        // that was previously resolved and has now recurred (e.g. a
        // service that failed again after being fixed) deserves a fresh
        // alert too — captured here, before fill() overwrites `status`.
        $isNewOrReopened = ! $finding->exists || $finding->status === SmartDetectionFinding::STATUS_RESOLVED;

        if (! $finding->exists) {
            $finding->first_detected_at = $now;
        }

        $finding->fill([
            'severity' => $spec['severity'],
            'title' => $spec['title'],
            'detail' => $spec['detail'] ?? null,
            'status' => SmartDetectionFinding::STATUS_OPEN,
            'last_detected_at' => $now,
            'resolved_at' => null,
        ]);

        $finding->save();

        return ['finding' => $finding, 'is_new_or_reopened' => $isNewOrReopened];
    }
}
