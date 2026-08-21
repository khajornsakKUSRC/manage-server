<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramNotifier
{
    /**
     * Sends a text message via the Telegram Bot API. Returns false (rather
     * than throwing) when no bot token/chat id is given, or when the
     * request fails — a notification failure should never break whatever
     * triggered it.
     */
    public function sendMessage(?string $botToken, ?string $chatId, string $text): bool
    {
        if (! $botToken || ! $chatId) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                report(new RuntimeException('Telegram sendMessage failed: '.$response->body()));
            }

            return $response->successful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Sends a file (e.g. a generated PDF) as a Telegram document attachment.
     * Same failure behavior as sendMessage() — returns false rather than
     * throwing.
     */
    public function sendDocument(
        ?string $botToken,
        ?string $chatId,
        string $filename,
        string $contents,
        ?string $caption = null,
    ): bool {
        if (! $botToken || ! $chatId) {
            return false;
        }

        try {
            $request = Http::timeout(30)->attach('document', $contents, $filename);

            $payload = ['chat_id' => $chatId];

            if ($caption !== null) {
                $payload['caption'] = $caption;
            }

            $response = $request->post("https://api.telegram.org/bot{$botToken}/sendDocument", $payload);

            if ($response->failed()) {
                report(new RuntimeException('Telegram sendDocument failed: '.$response->body()));
            }

            return $response->successful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
