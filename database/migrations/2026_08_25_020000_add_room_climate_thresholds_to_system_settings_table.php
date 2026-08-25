<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->float('room_temp_min_c')->default(18)->after('disabled_pages');
            $table->float('room_temp_max_c')->default(27)->after('room_temp_min_c');
            $table->float('room_humidity_min_pct')->default(40)->after('room_temp_max_c');
            $table->float('room_humidity_max_pct')->default(60)->after('room_humidity_min_pct');
        });

        // SystemSetting::current() caches its row forever — see that model
        // and the disabled_pages migration for the same note.
        Cache::forget('system_settings');
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'room_temp_min_c',
                'room_temp_max_c',
                'room_humidity_min_pct',
                'room_humidity_max_pct',
            ]);
        });
    }
};
