<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->string('inspector')->nullable()->after('imported_by');
            $table->json('checklist')->nullable()->after('summary');
            $table->boolean('overall_normal')->nullable()->after('checklist');
            $table->text('reason_text')->nullable()->after('overall_normal');
            $table->string('source')->default('import')->after('reason_text');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn(['inspector', 'checklist', 'overall_normal', 'reason_text', 'source']);
        });
    }
};
