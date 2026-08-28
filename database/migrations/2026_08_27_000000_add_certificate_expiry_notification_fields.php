<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedInteger('certificate_exp_warning_days')->default(30)->after('datastore_critical_pct');
        });

        Schema::table('vms', function (Blueprint $table) {
            // The certificate_exp value a Telegram notification was last
            // sent for — lets checkAndNotify() tell "already warned about
            // this exact expiry date" apart from "this is a new/renewed
            // date", without needing a separate notified-log table. A
            // renewal changes certificate_exp, so it naturally no longer
            // matches this and is free to notify again.
            $table->string('certificate_notified_exp')->nullable()->after('certificate_exp');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('certificate_exp_warning_days');
        });

        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn('certificate_notified_exp');
        });
    }
};
