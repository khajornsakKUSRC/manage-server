<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class GuestSshService
{
    protected const CONNECT_TIMEOUT_SECONDS = 10;

    // The direct-root-login probe is expected to fail on most of this
    // fleet, so it gets a shorter connect budget than the real (su-based)
    // path — a slow/unreachable host shouldn't cost 10s just to learn the
    // probe won't work.
    protected const ROOT_PROBE_TIMEOUT_SECONDS = 5;

    // How long to remember, per host, which auth path actually worked, so
    // repeat calls (services:check every N min, smart-detection:scan over
    // the whole fleet) skip the doomed root-login probe entirely. Kept
    // short enough that a host whose SSH policy changes re-probes within
    // the hour on its own.
    protected const AUTH_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        protected SshSuEscalation $suEscalation,
    ) {}

    /**
     * Connects to the given VM over SSH and runs one command, returning
     * its raw stdout+stderr. Tries direct root login first (GUEST_SSH_*)
     * — still works on some hosts — and falls back to a regular account
     * + `su -` (SSH_FALLBACK_USERNAME/PASSWORD + SSH_FALLBACK_SU_PASSWORD,
     * one shared account/policy across this network) when that's
     * rejected, which is the case on most of this fleet's Linux VMs.
     *
     * The working path is cached per host (see AUTH_CACHE_TTL_SECONDS) so
     * we don't pay for a full TCP+SSH handshake on the root probe every
     * call just to watch it fail. Every connection this opens is
     * explicitly disconnected in a finally block rather than left for GC.
     */
    public function run(string $host, string $command, int $execTimeoutSeconds = 20): string
    {
        $port = (int) config('services.guest_ssh.port', 22);
        $rootUsername = config('services.guest_ssh.username');
        $rootPassword = config('services.guest_ssh.password');

        $rootConfigured = $rootUsername && $rootPassword;
        $cacheKey = 'ssh_auth_method:'.$host;

        // Only bother with the root probe when it's configured AND we
        // haven't already learned this host rejects it.
        if ($rootConfigured && Cache::get($cacheKey) !== 'su') {
            $ssh = new SSH2($host, $port, self::ROOT_PROBE_TIMEOUT_SECONDS);

            try {
                if ($ssh->login($rootUsername, $rootPassword)) {
                    $ssh->setTimeout($execTimeoutSeconds);
                    $output = (string) $ssh->exec($command);
                    Cache::put($cacheKey, 'root', self::AUTH_CACHE_TTL_SECONDS);

                    return $output;
                }
            } catch (Throwable) {
                // Falls through to the su-based fallback below — a
                // connection-level failure here doesn't necessarily mean
                // the fallback account/host combination will fail too.
            } finally {
                $ssh->disconnect();
            }
        }

        $output = $this->runViaSu($host, $port, $command, $execTimeoutSeconds);

        if ($rootConfigured) {
            Cache::put($cacheKey, 'su', self::AUTH_CACHE_TTL_SECONDS);
        }

        return $output;
    }

    private function runViaSu(string $host, int $port, string $command, int $execTimeoutSeconds): string
    {
        $fallbackUsername = config('services.guest_ssh.fallback_username');
        $fallbackPassword = config('services.guest_ssh.fallback_password');
        $suPassword = config('services.guest_ssh.su_password');

        if (! $fallbackUsername || ! $fallbackPassword || ! $suPassword) {
            throw new RuntimeException('กรุณาตั้งค่า GUEST_SSH_USERNAME/PASSWORD หรือ SSH_FALLBACK_USERNAME/PASSWORD และ SSH_FALLBACK_SU_PASSWORD ในไฟล์ .env');
        }

        $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);

        try {
            try {
                $loggedIn = $ssh->login($fallbackUsername, $fallbackPassword);
            } catch (Throwable $e) {
                throw new RuntimeException("ไม่สามารถเชื่อมต่อ SSH ไปยัง {$host} ได้: ".$e->getMessage());
            }

            if (! $loggedIn) {
                throw new RuntimeException("เข้าสู่ระบบ SSH ที่ {$host} ไม่สำเร็จ กรุณาตรวจสอบ username/password: ".$ssh->getLastError());
            }

            return $this->suEscalation->runAsRoot($ssh, $suPassword, $command, self::CONNECT_TIMEOUT_SECONDS, $execTimeoutSeconds);
        } finally {
            $ssh->disconnect();
        }
    }
}
