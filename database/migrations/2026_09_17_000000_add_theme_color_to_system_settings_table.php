<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Key into App\Support\ThemePalette::THEMES — 'blue_purple' is
            // the app's current default (see resources/css/app.css).
            $table->string('theme_color', 30)->default('blue_purple')->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};
