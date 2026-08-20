<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropForeign(['imported_by']);
            $table->dropColumn([
                'original_filename',
                'imported_by',
                'inspector',
                'summary',
                'checklist',
                'overall_normal',
                'reason_text',
                'source',
            ]);
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('report_date')->constrained('users')->nullOnDelete();
            $table->string('incident')->nullable()->after('created_by');
            $table->string('action')->nullable()->after('incident');
            $table->text('remark')->nullable()->after('action');
        });

        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->dropColumn([
                'dns',
                'state',
                'status',
                'provisioned_space',
                'used_space',
                'host_cpu',
                'host_mem',
            ]);
        });

        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->string('power_state')->nullable()->after('host');
            $table->unsignedInteger('cpu_count')->nullable()->after('power_state');
            $table->decimal('memory_gb', 8, 2)->nullable()->after('cpu_count');
            $table->decimal('disk_usage_pct', 5, 2)->nullable()->after('memory_gb');
            $table->unsignedBigInteger('uptime_seconds')->nullable()->after('disk_usage_pct');
        });
    }

    public function down(): void
    {
        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->dropColumn([
                'power_state',
                'cpu_count',
                'memory_gb',
                'disk_usage_pct',
                'uptime_seconds',
            ]);
        });

        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->string('state')->nullable();
            $table->string('status')->nullable();
            $table->string('provisioned_space')->nullable();
            $table->string('used_space')->nullable();
            $table->string('host_cpu')->nullable();
            $table->string('host_mem')->nullable();
            $table->string('dns')->nullable()->after('host');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'incident', 'action', 'remark']);
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->string('original_filename')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspector')->nullable();
            $table->text('summary')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('overall_normal')->nullable();
            $table->text('reason_text')->nullable();
            $table->string('source')->default('import');
        });
    }
};
