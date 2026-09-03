<?php

namespace App\Services;

use App\Models\ItRepairRequest;
use Illuminate\Support\Str;
use Throwable;

/**
 * Telegram alert to the IT team the moment a repair request comes in —
 * from the public form (/it-repair/new) or filed by staff on /it-repair.
 * Uses its own bot/chat (services.telegram_it_repair) so these don't mix
 * with the infra alarms. Silently no-ops when that bot isn't configured.
 */
class ItRepairNotificationService
{
    public function __construct(
        protected TelegramNotifier $telegram,
    ) {}

    public function notifyNewRequest(ItRepairRequest $repair): void
    {
        try {
            $this->telegram->sendMessage(
                config('services.telegram_it_repair.bot_token'),
                config('services.telegram_it_repair.chat_id'),
                $this->formatMessage($repair),
            );
        } catch (Throwable $e) {
            // A notification must never break the submission it followed.
            report($e);
        }
    }

    protected function formatMessage(ItRepairRequest $repair): string
    {
        $lines = [
            '🔧 <b>มีคำขอแจ้งซ่อมใหม่</b> #'.$repair->id,
            '',
            'ผู้แจ้ง: <b>'.$this->escape($repair->full_name).'</b>',
            'อีเมล: '.$this->escape($repair->recipient_email),
            'เบอร์ติดต่อ: '.$this->escape($repair->contact_number),
            'ประเภทงาน: '.$this->escape($repair->service_type),
            'ผู้รับผิดชอบ: '.$this->escape($repair->provider_name ?: '-'),
            'วันที่แจ้ง: '.$this->escape($repair->requested_at->format('d/m/Y H:i')),
        ];

        if ($repair->createdBy) {
            $lines[] = 'บันทึกโดยเจ้าหน้าที่: '.$this->escape($repair->createdBy->name);
        }

        $details = trim((string) $repair->details);

        if ($details !== '') {
            $lines[] = '';
            $lines[] = 'รายละเอียด:';
            $lines[] = $this->escape(Str::limit($details, 600));
        }

        $lines[] = '';
        $lines[] = 'เปิดหน้าจัดการ: '.url('/it-repair');

        return implode("\n", $lines);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
