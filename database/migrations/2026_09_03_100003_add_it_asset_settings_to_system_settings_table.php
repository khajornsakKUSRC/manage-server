<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Targets the IT Asset KPI report (monthly / 6-month) measures against:
     * the share of assets inspected in the period, and the completion rate
     * of counting rounds. Editable on Settings later; sane defaults now.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('it_asset_inspection_target_pct')->default(90)->after('room_humidity_max_pct');
            $table->unsignedTinyInteger('it_asset_count_target_pct')->default(95)->after('it_asset_inspection_target_pct');
        });

        Cache::forget('system_settings');
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['it_asset_inspection_target_pct', 'it_asset_count_target_pct']);
        });

        Cache::forget('system_settings');
    }
};
