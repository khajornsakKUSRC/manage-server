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
            $table->integer('memory_gb')->nullable()->after('used_space');
            $table->integer('cpu_cores')->nullable()->after('memory_gb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn(['memory_gb', 'cpu_cores']);
        });
    }
};
