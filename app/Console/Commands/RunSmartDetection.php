<?php

namespace App\Console\Commands;

use App\Models\SmartDetectionFinding;
use App\Models\SystemSetting;
use App\Models\Vm;
use App\Services\SmartDetectionService;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Throwable;

class RunSmartDetection extends Command
{
    protected $signature = 'smart-detection:scan';

    protected $description = 'Runs Smart Detection (brute force, process, malware, port/service, and service-failure checks) over SSH against every active VM with an IP, alerting on new findings via Telegram';

    public function handle(SmartDetectionService $detectionService, TelegramNotifier $telegram): int
    {
        $vms = Vm::active()->whereNotNull('ip')->where('ip', '!=', '')->get();

        // Settings → Telegram Notifications → Smart Detection only mutes
        // the alert below — the scan itself (and the findings it stores
        // for the Smart Detection page) always runs on its configured
        // interval regardless.
        $notifyEnabled = SystemSetting::current()->notify_smart_detection_enabled;

        $scanned = 0;
        $alerted = 0;

        foreach ($vms as $vm) {
            try {
                $results = $detectionService->scanVm($vm);
            } catch (Throwable $e) {
                // A VM that isn't reachable over SSH (Windows, firewalled,
                // no/wrong credentials, offline) is expected and common
                // across a mixed fleet — skip it rather than treating
                // every one as an error worth reporting to Sentry/logs.
                $this->line("Skipped {$vm->name} ({$vm->ip}): ".$e->getMessage());

                continue;
            }

            $scanned++;

            foreach ($results as $result) {
                /** @var SmartDetectionFinding $finding */
                $finding = $result['finding'];

                // "info"-severity findings (new process observed) are
                // intentionally noisy by design — worth showing on the
                // Smart Detection page, not worth a Telegram alert each
                // time.
                if (! $result['is_new_or_reopened'] || $finding->severity === 'info') {
                    continue;
                }

                if (! $notifyEnabled) {
                    continue;
                }

                if ($telegram->sendMessage(
                    config('services.telegram.bot_token'),
                    config('services.telegram.chat_id'),
                    $this->formatMessage($vm, $finding),
                )) {
                    $alerted++;
                }
            }
        }

        $this->info("Scanned {$scanned} VM(s), sent {$alerted} Smart Detection alert(s).");

        return self::SUCCESS;
    }

    protected function formatMessage(Vm $vm, SmartDetectionFinding $finding): string
    {
        $emoji = $finding->severity === 'critical' ? '🔴' : '🟡';
        $category = SmartDetectionFinding::CATEGORIES[$finding->category] ?? $finding->category;

        $lines = [
            "{$emoji} <b>Smart Detection: {$this->escape($category)}</b>",
            'VM: <b>'.$this->escape($vm->name).'</b> ('.$this->escape((string) $vm->ip).')',
            $this->escape($finding->title),
        ];

        if ($finding->detail) {
            $lines[] = $this->escape($finding->detail);
        }

        return implode("\n", $lines);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
