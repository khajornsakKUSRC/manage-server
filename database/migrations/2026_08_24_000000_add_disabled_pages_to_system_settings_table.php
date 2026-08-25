<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->json('disabled_pages')->nullable()->after('session_timeout_minutes');
        });

        // Mod Security's menu is still a work in progress — start it out
        // globally disabled (on the existing settings row too) rather than
        // leaving every page enabled by default.
        DB::table('system_settings')->update([
            'disabled_pages' => json_encode(['modsecurity']),
        ]);

        // SystemSetting::current() caches its row forever (see that model) —
        // without forgetting it here, a server that already served a request
        // before this migration ran would keep serving the pre-migration
        // (no disabled_pages) cached settings indefinitely.
        Cache::forget('system_settings');
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('disabled_pages');
        });
    }
};
