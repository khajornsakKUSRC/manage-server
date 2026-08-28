<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class GuestSshService
{
    protected const CONNECT_TIMEOUT_SECONDS = 10;

    public function __construct(
        protected SshSuEscalation $suEscalation,
    ) {}

    /**
     * Connects to the given VM over SSH and runs one command, returning
     * its raw stdout+stderr. Tries direct root login first (GUEST_SSH_*)
     * — still works on some hosts — and falls back to a regular account
     * + `su -` (RADIUS_SSH_* / RADIUS_SU_PASSWORD — shared with the KUWIN
     * Radius page, since it's the same account/policy across this
     * network) when that's rejected, which is the case on most of this
     * fleet's Linux VMs. A fresh connection per call, same as
     * ModSecurityLogService — these are periodic/on-demand calls, not
     * frequent enough to justify a persistent connection pool.
     */
    public function run(string $host, string $command, int $execTimeoutSeconds = 20): string
    {
        $port = (int) config('services.guest_ssh.port', 22);
        $rootUsername = config('services.guest_ssh.username');
        $rootPassword = config('services.guest_ssh.password');

        if ($rootUsername && $rootPassword) {
            $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);

            try {
                if ($ssh->login($rootUsername, $rootPassword)) {
                    $ssh->setTimeout($execTimeoutSeconds);

                    return (string) $ssh->exec($command);
                }
            } catch (Throwable) {
                // Falls through to the su-based fallback below — a
                // connection-level failure here doesn't necessarily mean
                // the fallback account/host combination will fail too.
            }
        }

        return $this->runViaSu($host, $port, $command, $execTimeoutSeconds);
    }

    private function runViaSu(string $host, int $port, string $command, int $execTimeoutSeconds): string
    {
        $fallbackUsername = config('services.guest_ssh.fallback_username');
        $fallbackPassword = config('services.guest_ssh.fallback_password');
        $suPassword = config('services.guest_ssh.su_password');

        if (! $fallbackUsername || ! $fallbackPassword || ! $suPassword) {
            throw new RuntimeException('กรุณาตั้งค่า GUEST_SSH_USERNAME/PASSWORD หรือ RADIUS_SSH_USERNAME/PASSWORD และ RADIUS_SU_PASSWORD ในไฟล์ .env');
        }

        $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);

        try {
            $loggedIn = $ssh->login($fallbackUsername, $fallbackPassword);
        } catch (Throwable $e) {
            throw new RuntimeException("ไม่สามารถเชื่อมต่อ SSH ไปยัง {$host} ได้: ".$e->getMessage());
        }

        if (! $loggedIn) {
            throw new RuntimeException("เข้าสู่ระบบ SSH ที่ {$host} ไม่สำเร็จ กรุณาตรวจสอบ username/password: ".$ssh->getLastError());
        }

        return $this->suEscalation->runAsRoot($ssh, $suPassword, $command, self::CONNECT_TIMEOUT_SECONDS, $execTimeoutSeconds);
    }
}
