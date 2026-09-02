<?php

namespace App\Mail;

use App\Models\MonitoredService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ServiceDownMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}  $result
     */
    public function __construct(
        public MonitoredService $service,
        public array $result,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Service Down] {$this->service->label} — {$this->service->service_name} on {$this->service->host}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.service-down',
        );
    }
}
