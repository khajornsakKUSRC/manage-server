<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->string('update_status')->nullable()->after('used_space');
            $table->text('notes')->nullable()->after('update_status');
            $table->string('certificate_exp')->nullable()->after('notes');
            $table->boolean('is_active')->default(true)->after('certificate_exp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn(['update_status', 'notes', 'certificate_exp', 'is_active']);
        });
    }
};
