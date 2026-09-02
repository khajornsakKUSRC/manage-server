<?php

namespace App\Mail;

use App\Models\ItRepairRequest;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * The "Send Email" action on the IT Repair page. Header, subject, body, and
 * footer all come from Settings → IT Repair Notification Email
 * (system_settings.it_repair_email_*), each rendered against this one
 * request's data — see PLACEHOLDERS below.
 */
class ItRepairNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Tokens accepted in the header/subject/body/footer templates.
     *
     * @var array<int, string>
     */
    public const PLACEHOLDERS = [
        'full_name',
        'recipient_email',
        'contact_number',
        'service_type',
        'provider_name',
        'status',
        'status_label',
        'details',
        'requested_at',
        'request_id',
        'tracking_link',
    ];

    public string $headerText;

    public string $subjectText;

    public string $bodyText;

    public string $footerText;

    /**
     * The logo, served over http(s) rather than embedded as a CID
     * attachment — so it renders inline on the same "page" as the message
     * instead of arriving as a separate attachment. An admin-uploaded logo
     * (Settings → IT Repair Notification Email) wins; otherwise the bundled
     * KU logo at public/image/it-repair-email-logo-ku.png is used.
     */
    public string $logoUrl;

    public bool $showLogo;

    public int $logoWidth;

    public string $headingColor;

    public string $textColor;

    public string $backgroundColor;

    /** 'full' (edge-to-edge) or 'centered' (fixed-width column). */
    public string $layout;

    public int $contentWidth;

    public function __construct(
        public ItRepairRequest $repairRequest,
    ) {
        $settings = SystemSetting::current();

        $this->headerText = $this->interpolate($settings->it_repair_email_header ?: 'IT Repair Request Update');
        $this->subjectText = $this->interpolate($settings->it_repair_email_subject ?: 'IT Repair Request Update — {{full_name}}');
        $this->bodyText = $this->interpolate($settings->it_repair_email_body ?: 'Hello {{full_name}}, your IT repair request is now: {{status_label}}.');
        $this->footerText = $this->interpolate($settings->it_repair_email_footer ?: '');

        $this->logoUrl = $settings->it_repair_email_logo_path
            ? Storage::disk('public')->url($settings->it_repair_email_logo_path)
            : url('image/it-repair-email-logo-ku.png');
        $this->showLogo = (bool) ($settings->it_repair_email_show_logo ?? true);
        $this->logoWidth = (int) ($settings->it_repair_email_logo_width ?: 64);
        $this->headingColor = $settings->it_repair_email_heading_color ?: '#18181b';
        $this->textColor = $settings->it_repair_email_text_color ?: '#18181b';
        $this->backgroundColor = $settings->it_repair_email_background_color ?: '#ffffff';
        $this->layout = $settings->it_repair_email_layout === 'centered' ? 'centered' : 'full';
        $this->contentWidth = (int) ($settings->it_repair_email_content_width ?: 600);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.it-repair-notification',
        );
    }

    /**
     * Fills {{placeholder}} tokens with this request's own data. Unknown
     * tokens are left as-is rather than silently stripped, so a typo in the
     * template is visible in the sent email instead of vanishing.
     */
    protected function interpolate(string $template): string
    {
        $r = $this->repairRequest;

        return strtr($template, [
            '{{full_name}}' => $r->full_name,
            '{{recipient_email}}' => $r->recipient_email,
            '{{contact_number}}' => $r->contact_number,
            '{{service_type}}' => $r->service_type,
            '{{provider_name}}' => $r->provider_name ?: '-',
            '{{status}}' => $r->status,
            '{{status_label}}' => ItRepairRequest::STATUSES[$r->status] ?? $r->status,
            '{{details}}' => $r->details,
            '{{requested_at}}' => $r->requested_at->toDateTimeString(),
            '{{request_id}}' => (string) $r->id,
            // Body/footer are rendered as plain, escaped text (see
            // resources/views/emails/it-repair-notification.blade.php) —
            // deliberately, since {{details}} can carry an untrusted
            // public submission. So this stays a bare URL rather than an
            // <a> tag; virtually every email client auto-linkifies a bare
            // https:// URL in plain text on its own.
            '{{tracking_link}}' => url('/it-repair/new'),
        ]);
    }
}
