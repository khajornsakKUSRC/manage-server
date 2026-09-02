<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('notify_services_enabled')->default(true)->after('notify_certificate_check_time');
            $table->unsignedInteger('notify_services_interval_minutes')->default(20)->after('notify_services_enabled');
            // Superseded by notify_services_emails (a JSON add-then-permit
            // list) in the 2026_08_31 migration — kept here as the
            // original string column so that migration has something to
            // convert from on machines that ran this one first.
            $table->string('notify_services_email')->nullable()->after('notify_services_interval_minutes');
            $table->boolean('notify_services_telegram_enabled')->default(true)->after('notify_services_email');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_services_enabled',
                'notify_services_interval_minutes',
                'notify_services_email',
                'notify_services_telegram_enabled',
            ]);
        });
    }
};
