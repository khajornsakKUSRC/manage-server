<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['incident', 'action', 'remark']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->string('incident')->nullable()->after('created_by');
            $table->string('action')->nullable()->after('incident');
            $table->text('remark')->nullable()->after('action');
        });
    }
};
