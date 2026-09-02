<?php

namespace App\Models;

use App\Casts\SafeJsonArrayCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected const CACHE_KEY = 'system_settings';

    protected $fillable = [
        'maintenance_mode_enabled',
        'maintenance_message',
        'favicon_path',
        'timezone',
        'footer_text',
        'cpu_warning_pct',
        'cpu_critical_pct',
        'mem_warning_pct',
        'mem_critical_pct',
        'datastore_warning_pct',
        'datastore_critical_pct',
        'certificate_exp_warning_days',
        'notify_alarms_enabled',
        'notify_alarms_interval_minutes',
        'notify_smart_detection_enabled',
        'notify_smart_detection_interval_minutes',
        'notify_network_wan_enabled',
        'notify_certificate_enabled',
        'notify_certificate_check_time',
        'notify_services_enabled',
        'notify_services_interval_minutes',
        'notify_services_emails',
        'notify_services_telegram_enabled',
        'session_timeout_minutes',
        'disabled_pages',
        'room_temp_min_c',
        'room_temp_max_c',
        'room_humidity_min_pct',
        'room_humidity_max_pct',
        'it_repair_email_header',
        'it_repair_email_subject',
        'it_repair_email_body',
        'it_repair_email_footer',
        'it_repair_email_logo_path',
        'it_repair_email_show_logo',
        'it_repair_email_logo_width',
        'it_repair_email_heading_color',
        'it_repair_email_text_color',
        'it_repair_email_background_color',
        'it_repair_email_layout',
        'it_repair_email_content_width',
    ];

    protected $casts = [
        'maintenance_mode_enabled' => 'boolean',
        'cpu_warning_pct' => 'integer',
        'cpu_critical_pct' => 'integer',
        'mem_warning_pct' => 'integer',
        'mem_critical_pct' => 'integer',
        'datastore_warning_pct' => 'integer',
        'datastore_critical_pct' => 'integer',
        'certificate_exp_warning_days' => 'integer',
        'notify_alarms_enabled' => 'boolean',
        'notify_alarms_interval_minutes' => 'integer',
        'notify_smart_detection_enabled' => 'boolean',
        'notify_smart_detection_interval_minutes' => 'integer',
        'notify_network_wan_enabled' => 'boolean',
        'notify_certificate_enabled' => 'boolean',
        'notify_services_enabled' => 'boolean',
        'notify_services_interval_minutes' => 'integer',
        'notify_services_emails' => SafeJsonArrayCast::class,
        'notify_services_telegram_enabled' => 'boolean',
        'session_timeout_minutes' => 'integer',
        'disabled_pages' => SafeJsonArrayCast::class,
        'room_temp_min_c' => 'float',
        'room_temp_max_c' => 'float',
        'room_humidity_min_pct' => 'float',
        'room_humidity_max_pct' => 'float',
        'it_repair_email_show_logo' => 'boolean',
        'it_repair_email_logo_width' => 'integer',
        'it_repair_email_content_width' => 'integer',
    ];

    /**
     * The single settings row, created with defaults on first use. Cached
     * indefinitely — this is read on every request (see
     * ApplySystemSettings) so it must not cost a query each time. Callers
     * that change settings MUST call forgetCache() afterward.
     *
     * Caches the plain attribute array, not the Eloquent instance itself —
     * Laravel's cache stores restrict unserialize() to an allow-listed set
     * of classes, so a raw cached model silently comes back as
     * __PHP_Incomplete_Class. Rehydrating via newFromBuilder() (the same
     * method the query builder itself uses) avoids that entirely.
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            $row = static::query()->first();

            // Deliberately not firstOrCreate(['id' => 1], [...]) — 'id'
            // isn't mass-assignable (not in $fillable), so it would be
            // silently dropped from the insert and a fresh row would get
            // whatever the next auto-increment value is instead of a
            // predictable id. There's only ever one row, so "first, or
            // create one with defaults" is all that actually matters.
            $row ??= static::query()->create([
                'maintenance_mode_enabled' => false,
                'timezone' => 'UTC',
                'cpu_warning_pct' => 70,
                'cpu_critical_pct' => 85,
                'mem_warning_pct' => 70,
                'mem_critical_pct' => 85,
                'datastore_warning_pct' => 70,
                'datastore_critical_pct' => 85,
                'certificate_exp_warning_days' => 30,
                'notify_alarms_enabled' => true,
                'notify_alarms_interval_minutes' => 1,
                'notify_smart_detection_enabled' => true,
                'notify_smart_detection_interval_minutes' => 15,
                'notify_network_wan_enabled' => true,
                'notify_certificate_enabled' => true,
                'notify_certificate_check_time' => '08:00',
                'notify_services_enabled' => true,
                'notify_services_interval_minutes' => 20,
                'notify_services_emails' => [],
                'notify_services_telegram_enabled' => true,
                'session_timeout_minutes' => 120,
                'disabled_pages' => ['modsecurity'],
                'room_temp_min_c' => 18,
                'room_temp_max_c' => 27,
                'room_humidity_min_pct' => 40,
                'room_humidity_max_pct' => 60,
                'it_repair_email_header' => 'IT Repair Request Update',
                'it_repair_email_subject' => 'IT Repair Request Update — {{full_name}}',
                'it_repair_email_body' => "Hello {{full_name}},\n\nThis is an update on your IT repair request (#{{request_id}}).\n\nService type: {{service_type}}\nProvider: {{provider_name}}\nCurrent status: {{status_label}}\n\nDetails:\n{{details}}\n\nTrack your request: {{tracking_link}}",
                'it_repair_email_footer' => 'This is an automated message — please do not reply directly to this email.',
                'it_repair_email_logo_path' => null,
                'it_repair_email_show_logo' => true,
                'it_repair_email_logo_width' => 64,
                'it_repair_email_heading_color' => '#18181b',
                'it_repair_email_text_color' => '#18181b',
                'it_repair_email_background_color' => '#ffffff',
                'it_repair_email_layout' => 'full',
                'it_repair_email_content_width' => 600,
            ]);

            return $row->getAttributes();
        });

        return (new static)->newFromBuilder($attributes);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
