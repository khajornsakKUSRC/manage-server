<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template for the "Send Email" action on the IT Repair page — the
     * admin-configurable header/subject/details/footer used when notifying
     * a request's recipient. Each field may contain {{placeholder}} tokens
     * (see App\Mail\ItRepairNotificationMail) that are filled in per
     * request at send time.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('it_repair_email_header')->nullable();
            $table->string('it_repair_email_subject')->nullable();
            $table->text('it_repair_email_body')->nullable();
            $table->text('it_repair_email_footer')->nullable();
        });

        // Seed the existing row (created by an earlier migration, so
        // SystemSetting::current()'s create-with-defaults block never ran
        // for it) with the same starting template a fresh install gets —
        // otherwise the Settings page would show four blank fields.
        DB::table('system_settings')->whereNull('it_repair_email_body')->update([
            'it_repair_email_header' => 'IT Repair Request Update',
            'it_repair_email_subject' => 'IT Repair Request Update — {{full_name}}',
            'it_repair_email_body' => "Hello {{full_name}},\n\nThis is an update on your IT repair request (#{{request_id}}).\n\nService type: {{service_type}}\nProvider: {{provider_name}}\nCurrent status: {{status_label}}\n\nDetails:\n{{details}}\n\nTrack your request: {{tracking_link}}",
            'it_repair_email_footer' => 'This is an automated message — please do not reply directly to this email.',
        ]);

        // SystemSetting::current() caches its row forever — see that model
        // and the disabled_pages migration for the same note.
        Cache::forget('system_settings');
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'it_repair_email_header',
                'it_repair_email_subject',
                'it_repair_email_body',
                'it_repair_email_footer',
            ]);
        });
    }
};
