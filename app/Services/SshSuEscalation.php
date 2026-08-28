<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use RuntimeException;

/**
 * Runs one command as root over an interactive `su -` shell, for hosts
 * where direct root SSH login is disabled — the case on most of this
 * network's Linux fleet, not just the KUWIN Radius server this was first
 * built for. Shared by GuestSshService, ModSecurityLogService, and
 * RadiusLogService.
 */
class SshSuEscalation
{
    /**
     * $ssh must already be logged in as a regular (non-root) user with
     * permission to `su -`. $connectTimeoutSeconds bounds each individual
     * prompt exchange (login banner, "Password:", the root prompt);
     * $execTimeoutSeconds bounds waiting for the command itself to finish
     * — callers pulling a lot of data (e.g. a wide-range log export) pass
     * a longer one than a quick one-off status check would need.
     */
    public function runAsRoot(SSH2 $ssh, string $suPassword, string $command, int $connectTimeoutSeconds = 10, int $execTimeoutSeconds = 30): string
    {
        $ssh->setTimeout($connectTimeoutSeconds);

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
            throw new RuntimeException('su เป็น root ไม่สำเร็จ กรุณาตรวจสอบรหัสผ่าน su ใน .env');
        }

        // A random marker delimits the command's output from the shell's
        // next prompt, since an interactive shell has no clean EOF signal
        // the way exec() does. The shell echoes back whatever we typed
        // (including the literal "echo $marker" text) before the command
        // even runs, so the *first* time the marker appears in the stream
        // is just that echo — discard it and wait for the marker a second
        // time, which is the real `echo` output after the command finishes.
        $marker = 'SUESCEND_'.bin2hex(random_bytes(4));
        $ssh->setTimeout($execTimeoutSeconds);
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
}
