<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the single comma-separated `notify_services_email` string
     * with `notify_services_emails`: a JSON list of
     * {"email": string, "notify": bool}. An address is added to the list
     * first and only receives the Service Monitoring alert once its
     * "notify" permission is turned on (Settings → Notify Email). The old
     * string is carried over as one entry per address with notify = true,
     * since every address in it was previously being mailed.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('system_settings', 'notify_services_emails')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->json('notify_services_emails')->nullable()->after('notify_services_interval_minutes');
            });
        }

        if (Schema::hasColumn('system_settings', 'notify_services_email')) {
            foreach (DB::table('system_settings')->get(['id', 'notify_services_email']) as $row) {
                $list = collect(explode(',', (string) $row->notify_services_email))
                    ->map(fn ($email) => strtolower(trim($email)))
                    ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
                    ->unique()
                    ->map(fn ($email) => ['email' => $email, 'notify' => true])
                    ->values()
                    ->all();

                DB::table('system_settings')
                    ->where('id', $row->id)
                    ->update(['notify_services_emails' => json_encode($list)]);
            }

            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('notify_services_email');
            });
        }

        // First run of the seed lives in 2026_08_29_000000; re-seed here
        // only where that row was since removed and nothing replaced it,
        // so the Services page isn't empty on a machine that already had
        // this feature half-built.
        if (DB::table('monitored_services')->count() === 0) {
            DB::table('monitored_services')->insert([
                'label' => 'Postfix',
                'host' => '158.108.96.21',
                'service_name' => 'postfix',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('system_settings', 'notify_services_email')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->string('notify_services_email')->nullable()->after('notify_services_interval_minutes');
            });
        }

        foreach (DB::table('system_settings')->get(['id', 'notify_services_emails']) as $row) {
            $emails = collect(json_decode((string) $row->notify_services_emails, true) ?: [])
                ->filter(fn ($entry) => is_array($entry) && ! empty($entry['notify']) && ! empty($entry['email']))
                ->map(fn ($entry) => $entry['email'])
                ->implode(',');

            DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['notify_services_email' => $emails ?: null]);
        }

        if (Schema::hasColumn('system_settings', 'notify_services_emails')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('notify_services_emails');
            });
        }
    }
};
