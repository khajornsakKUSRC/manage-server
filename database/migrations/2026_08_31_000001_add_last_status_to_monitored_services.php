<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caches the last observed status on each monitored_services row so
     * the Services page can render instantly from the DB instead of
     * SSHing into every host on every page view / HMR reload. The
     * scheduled services:check writes these; the page only does a live
     * check when the user explicitly hits "Refresh".
     */
    public function up(): void
    {
        Schema::table('monitored_services', function (Blueprint $table) {
            $table->string('last_status')->nullable()->after('service_name');
            $table->boolean('last_healthy')->nullable()->after('last_status');
            $table->text('last_detail')->nullable()->after('last_healthy');
            $table->text('last_raw')->nullable()->after('last_detail');
            $table->timestamp('last_checked_at')->nullable()->after('last_raw');
        });

        // Undo the 2-minute interval left behind by a one-off notification
        // test — 20 minutes is the intended default (matches the column
        // default and SystemSetting::current()). Only touches a row still
        // sitting at that test value.
        DB::table('system_settings')
            ->where('notify_services_interval_minutes', 2)
            ->update(['notify_services_interval_minutes' => 20]);
    }

    public function down(): void
    {
        Schema::table('monitored_services', function (Blueprint $table) {
            $table->dropColumn([
                'last_status',
                'last_healthy',
                'last_detail',
                'last_raw',
                'last_checked_at',
            ]);
        });
    }
};
