<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // "Enabled" gates the Telegram send itself. For Alarm
            // Notification and Certificate Expiration that also means the
            // whole scheduled command is skipped (nothing else depends on
            // them running). For Smart Detection and Network
            // Infrastructure (WAN), the underlying scan/check keeps running
            // on schedule regardless — their pages depend on that data —
            // only the Telegram alert inside it is muted. See
            // routes/console.php and the respective command classes.
            $table->boolean('notify_alarms_enabled')->default(true)->after('certificate_exp_warning_days');
            $table->unsignedInteger('notify_alarms_interval_minutes')->default(5)->after('notify_alarms_enabled');

            $table->boolean('notify_smart_detection_enabled')->default(true)->after('notify_alarms_interval_minutes');
            $table->unsignedInteger('notify_smart_detection_interval_minutes')->default(15)->after('notify_smart_detection_enabled');

            $table->boolean('notify_network_wan_enabled')->default(true)->after('notify_smart_detection_interval_minutes');

            $table->boolean('notify_certificate_enabled')->default(true)->after('notify_network_wan_enabled');
            $table->string('notify_certificate_check_time', 5)->default('08:00')->after('notify_certificate_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_alarms_enabled',
                'notify_alarms_interval_minutes',
                'notify_smart_detection_enabled',
                'notify_smart_detection_interval_minutes',
                'notify_network_wan_enabled',
                'notify_certificate_enabled',
                'notify_certificate_check_time',
            ]);
        });
    }
};
