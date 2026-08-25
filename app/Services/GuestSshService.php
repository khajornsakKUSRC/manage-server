<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class GuestSshService
{
    protected const CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Connects to the given VM over SSH (the shared GUEST_SSH_* credential
     * — see config/services.php) and runs one command, returning its raw
     * stdout+stderr. A fresh connection per call, same as
     * ModSecurityLogService — these are periodic/on-demand calls, not
     * frequent enough to justify a persistent connection pool.
     */
    public function run(string $host, string $command, int $execTimeoutSeconds = 20): string
    {
        $username = config('services.guest_ssh.username');
        $password = config('services.guest_ssh.password');
        $port = (int) config('services.guest_ssh.port', 22);

        if (! $username || ! $password) {
            throw new RuntimeException('กรุณาตั้งค่า GUEST_SSH_USERNAME และ GUEST_SSH_PASSWORD ในไฟล์ .env');
        }

        $ssh = new SSH2($host, $port, self::CONNECT_TIMEOUT_SECONDS);
        $ssh->setTimeout($execTimeoutSeconds);

        try {
            $loggedIn = $ssh->login($username, $password);
        } catch (Throwable $e) {
            throw new RuntimeException("ไม่สามารถเชื่อมต่อ SSH ไปยัง {$host} ได้: ".$e->getMessage());
        }

        if (! $loggedIn) {
            throw new RuntimeException("เข้าสู่ระบบ SSH ที่ {$host} ไม่สำเร็จ กรุณาตรวจสอบ username/password: ".$ssh->getLastError());
        }

        return (string) $ssh->exec($command);
    }
}
