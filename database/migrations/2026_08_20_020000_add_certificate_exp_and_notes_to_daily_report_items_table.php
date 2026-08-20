<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->string('certificate_exp')->nullable()->after('uptime_seconds');
            $table->text('notes')->nullable()->after('certificate_exp');
        });
    }

    public function down(): void
    {
        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->dropColumn(['certificate_exp', 'notes']);
        });
    }
};
