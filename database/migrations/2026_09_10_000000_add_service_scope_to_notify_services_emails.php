<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extends each notify_services_emails entry from {email, notify} to
     * {email, notify, all_services, service_ids}: an address can now be
     * scoped to a subset of monitored services rather than always
     * receiving every Service Monitoring alert. Existing rows get
     * all_services = true, which preserves today's "notified about every
     * service" behaviour.
     */
    public function up(): void
    {
        foreach (DB::table('system_settings')->get(['id', 'notify_services_emails']) as $row) {
            $list = collect(json_decode((string) $row->notify_services_emails, true) ?: [])
                ->filter(fn ($entry) => is_array($entry) && ! empty($entry['email']))
                ->map(fn (array $entry) => [
                    'email' => $entry['email'],
                    'notify' => (bool) ($entry['notify'] ?? false),
                    'all_services' => $entry['all_services'] ?? true,
                    'service_ids' => array_values(array_map('intval', $entry['service_ids'] ?? [])),
                ])
                ->values()
                ->all();

            DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['notify_services_emails' => json_encode($list)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('system_settings')->get(['id', 'notify_services_emails']) as $row) {
            $list = collect(json_decode((string) $row->notify_services_emails, true) ?: [])
                ->filter(fn ($entry) => is_array($entry) && ! empty($entry['email']))
                ->map(fn (array $entry) => [
                    'email' => $entry['email'],
                    'notify' => (bool) ($entry['notify'] ?? false),
                ])
                ->values()
                ->all();

            DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['notify_services_emails' => json_encode($list)]);
        }
    }
};
