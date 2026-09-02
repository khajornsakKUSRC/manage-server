<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `remind_at` becomes a full date + time so a notice can be scheduled
     * for a specific moment, and `reminded_at` records when the Telegram
     * reminder actually went out so calendar-notices:notify fires it once
     * and only once.
     */
    public function up(): void
    {
        Schema::table('calendar_notices', function (Blueprint $table) {
            $table->dateTime('remind_at')->nullable()->change();
            $table->timestamp('reminded_at')->nullable()->after('remind_at');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_notices', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
            $table->date('remind_at')->nullable()->change();
        });
    }
};
