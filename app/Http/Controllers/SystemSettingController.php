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
                'session_timeout_minutes' => $settings->session_timeout_minutes,
                'disabled_pages' => $settings->disabled_pages ?? [],
                'room_temp_min_c' => $settings->room_temp_min_c,
                'room_temp_max_c' => $settings->room_temp_max_c,
                'room_humidity_min_pct' => $settings->room_humidity_min_pct,
                'room_humidity_max_pct' => $settings->room_humidity_max_pct,
            ],
            'timezones' => timezone_identifiers_list(),
            'pages' => Permissions::PAGES,
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
            'session_timeout_minutes' => 'required|integer|min:1|max:43200',
            'disabled_pages' => 'array',
            'disabled_pages.*' => [Rule::in(Permissions::keys())],
            'room_temp_min_c' => 'required|numeric|min:-40|max:100',
            'room_temp_max_c' => 'required|numeric|min:-40|max:100|gte:room_temp_min_c',
            'room_humidity_min_pct' => 'required|numeric|min:0|max:100',
            'room_humidity_max_pct' => 'required|numeric|min:0|max:100|gte:room_humidity_min_pct',
        ]);

        $settings = SystemSetting::current();

        $attributes = collect($validated)
            ->except(['favicon', 'remove_favicon'])
            ->all();

        // Inertia's FormData serializer (required here for the favicon
        // upload) emits nothing at all for an empty array, so re-enabling
        // the last disabled page — leaving disabled_pages empty — sends no
        // "disabled_pages" field and $validated wouldn't have the key.
        // Default it explicitly rather than silently leaving the column
        // (and the previously-disabled page) untouched.
        $attributes['disabled_pages'] = $validated['disabled_pages'] ?? [];

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
