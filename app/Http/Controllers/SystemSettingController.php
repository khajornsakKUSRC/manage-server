<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function index(): Response
    {
        $settings = SystemSetting::current();

        return Inertia::render('system-settings/index', [
            'settings' => [
                'maintenance_mode_enabled' => $settings->maintenance_mode_enabled,
                'maintenance_message' => $settings->maintenance_message,
                'favicon_url' => $settings->favicon_path
                    ? Storage::disk('public')->url($settings->favicon_path)
                    : null,
                'timezone' => $settings->timezone,
                'footer_text' => $settings->footer_text,
                'cpu_warning_pct' => $settings->cpu_warning_pct,
                'cpu_critical_pct' => $settings->cpu_critical_pct,
                'mem_warning_pct' => $settings->mem_warning_pct,
                'mem_critical_pct' => $settings->mem_critical_pct,
                'datastore_warning_pct' => $settings->datastore_warning_pct,
                'datastore_critical_pct' => $settings->datastore_critical_pct,
                'certificate_exp_warning_days' => $settings->certificate_exp_warning_days,
                'notify_alarms_enabled' => $settings->notify_alarms_enabled,
                'notify_alarms_interval_minutes' => $settings->notify_alarms_interval_minutes,
                'notify_smart_detection_enabled' => $settings->notify_smart_detection_enabled,
                'notify_smart_detection_interval_minutes' => $settings->notify_smart_detection_interval_minutes,
                'notify_network_wan_enabled' => $settings->notify_network_wan_enabled,
                'notify_certificate_enabled' => $settings->notify_certificate_enabled,
                'notify_certificate_check_time' => $settings->notify_certificate_check_time,
                'notify_services_enabled' => $settings->notify_services_enabled,
                'notify_services_interval_minutes' => $settings->notify_services_interval_minutes,
                'notify_services_emails' => $settings->notify_services_emails ?? [],
                'notify_services_telegram_enabled' => $settings->notify_services_telegram_enabled,
                'session_timeout_minutes' => $settings->session_timeout_minutes,
                'disabled_pages' => $settings->disabled_pages ?? [],
                'room_temp_min_c' => $settings->room_temp_min_c,
                'room_temp_max_c' => $settings->room_temp_max_c,
                'room_humidity_min_pct' => $settings->room_humidity_min_pct,
                'room_humidity_max_pct' => $settings->room_humidity_max_pct,
                'it_repair_email_header' => $settings->it_repair_email_header,
                'it_repair_email_subject' => $settings->it_repair_email_subject,
                'it_repair_email_body' => $settings->it_repair_email_body,
                'it_repair_email_footer' => $settings->it_repair_email_footer,
                'it_repair_email_logo_url' => $settings->it_repair_email_logo_path
                    ? Storage::disk('public')->url($settings->it_repair_email_logo_path)
                    : null,
                'it_repair_email_show_logo' => $settings->it_repair_email_show_logo,
                'it_repair_email_logo_width' => $settings->it_repair_email_logo_width,
                'it_repair_email_heading_color' => $settings->it_repair_email_heading_color,
                'it_repair_email_text_color' => $settings->it_repair_email_text_color,
                'it_repair_email_background_color' => $settings->it_repair_email_background_color,
                'it_repair_email_layout' => $settings->it_repair_email_layout,
                'it_repair_email_content_width' => $settings->it_repair_email_content_width,
            ],
            'timezones' => timezone_identifiers_list(),
            'pages' => Permissions::PAGES,
            'telegramStatus' => [
                'main_configured' => filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id')),
                'daily_report_configured' => filled(config('services.telegram_daily_report.bot_token')) && filled(config('services.telegram_daily_report.chat_id')),
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_mode_enabled' => 'boolean',
            'maintenance_message' => 'nullable|string|max:2000',
            'favicon' => 'nullable|image|max:512',
            'remove_favicon' => 'boolean',
            'timezone' => ['required', 'string', 'timezone'],
            'footer_text' => 'nullable|string|max:255',
            'cpu_warning_pct' => 'required|integer|min:0|max:100',
            'cpu_critical_pct' => 'required|integer|min:0|max:100|gte:cpu_warning_pct',
            'mem_warning_pct' => 'required|integer|min:0|max:100',
            'mem_critical_pct' => 'required|integer|min:0|max:100|gte:mem_warning_pct',
            'datastore_warning_pct' => 'required|integer|min:0|max:100',
            'datastore_critical_pct' => 'required|integer|min:0|max:100|gte:datastore_warning_pct',
            'certificate_exp_warning_days' => 'required|integer|min:1|max:365',
            'notify_alarms_enabled' => 'boolean',
            'notify_alarms_interval_minutes' => 'required|integer|min:1|max:60',
            'notify_smart_detection_enabled' => 'boolean',
            'notify_smart_detection_interval_minutes' => 'required|integer|min:1|max:60',
            'notify_network_wan_enabled' => 'boolean',
            'notify_certificate_enabled' => 'boolean',
            'notify_certificate_check_time' => 'required|date_format:H:i',
            'notify_services_enabled' => 'boolean',
            'notify_services_interval_minutes' => 'required|integer|min:1|max:60',
            'notify_services_emails' => 'array|max:50',
            'notify_services_emails.*.email' => 'required|email|max:255|distinct:ignore_case',
            'notify_services_emails.*.notify' => 'boolean',
            'notify_services_telegram_enabled' => 'boolean',
            'session_timeout_minutes' => 'required|integer|min:1|max:43200',
            'disabled_pages' => 'array',
            'disabled_pages.*' => [Rule::in(Permissions::keys())],
            'room_temp_min_c' => 'required|numeric|min:-40|max:100',
            'room_temp_max_c' => 'required|numeric|min:-40|max:100|gte:room_temp_min_c',
            'room_humidity_min_pct' => 'required|numeric|min:0|max:100',
            'room_humidity_max_pct' => 'required|numeric|min:0|max:100|gte:room_humidity_min_pct',
            'it_repair_email_header' => 'nullable|string|max:255',
            'it_repair_email_subject' => 'nullable|string|max:255',
            'it_repair_email_body' => 'nullable|string|max:5000',
            'it_repair_email_footer' => 'nullable|string|max:1000',
            'it_repair_email_logo' => 'nullable|image|max:1024',
            'remove_it_repair_email_logo' => 'boolean',
            'it_repair_email_show_logo' => 'boolean',
            'it_repair_email_logo_width' => 'required|integer|min:16|max:400',
            'it_repair_email_heading_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'it_repair_email_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'it_repair_email_background_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'it_repair_email_layout' => ['required', Rule::in(['full', 'centered'])],
            'it_repair_email_content_width' => 'required|integer|min:320|max:1200',
        ]);

        $settings = SystemSetting::current();

        $attributes = collect($validated)
            ->except(['favicon', 'remove_favicon', 'it_repair_email_logo', 'remove_it_repair_email_logo'])
            ->all();

        // Inertia's FormData serializer (required here for the favicon
        // upload) emits nothing at all for an empty array, so re-enabling
        // the last disabled page — leaving disabled_pages empty — sends no
        // "disabled_pages" field and $validated wouldn't have the key.
        // Default it explicitly rather than silently leaving the column
        // (and the previously-disabled page) untouched.
        $attributes['disabled_pages'] = $validated['disabled_pages'] ?? [];

        // Same FormData-drops-empty-arrays reasoning as disabled_pages
        // above. Re-shape each row to exactly {email, notify:bool} so the
        // stored JSON never carries the "1"/"0" strings FormData sends for
        // booleans, and lowercase the address so the list can't hold the
        // same recipient twice in different case.
        $attributes['notify_services_emails'] = collect($validated['notify_services_emails'] ?? [])
            ->map(fn (array $row) => [
                'email' => strtolower(trim($row['email'])),
                'notify' => filter_var($row['notify'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        if ($request->boolean('remove_favicon') && $settings->favicon_path) {
            Storage::disk('public')->delete($settings->favicon_path);
            $attributes['favicon_path'] = null;
        }

        if ($request->hasFile('favicon')) {
            if ($settings->favicon_path) {
                Storage::disk('public')->delete($settings->favicon_path);
            }

            $attributes['favicon_path'] = $request->file('favicon')->store('favicon', 'public');
        }

        if ($request->boolean('remove_it_repair_email_logo') && $settings->it_repair_email_logo_path) {
            Storage::disk('public')->delete($settings->it_repair_email_logo_path);
            $attributes['it_repair_email_logo_path'] = null;
        }

        if ($request->hasFile('it_repair_email_logo')) {
            if ($settings->it_repair_email_logo_path) {
                Storage::disk('public')->delete($settings->it_repair_email_logo_path);
            }

            $attributes['it_repair_email_logo_path'] = $request->file('it_repair_email_logo')->store('it-repair-email', 'public');
        }

        $settings->update($attributes);
        SystemSetting::forgetCache();

        $activityLogger->record(
            action: 'updated',
            description: 'Updated system settings',
            subjectType: 'system_settings',
        );

        return redirect()->route('system-settings.index')->with('success', 'Settings updated successfully.');
    }
}
