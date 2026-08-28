<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            // Per-VM override of Settings → Certificate Expiration Warning
            // (days) — null means "use the global default". Lets a
            // slower-to-renew certificate get an earlier reminder (e.g. 60
            // days) without changing the warning window for every other VM.
            $table->unsignedInteger('certificate_notify_days')->nullable()->after('certificate_notified_exp');
        });
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn('certificate_notify_days');
        });
    }
};
