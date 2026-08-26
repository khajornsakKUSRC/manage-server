<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('type');
            $table->string('target');
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->unsignedInteger('timeout_ms')->default(3000);
            $table->boolean('is_active')->default(true);

            // Cached copy of the latest check result, so the status list can
            // render instantly without joining network_monitor_checks — the
            // authoritative history still lives there.
            $table->string('last_status')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->string('last_message')->nullable();

            $table->timestamps();

            $table->index(['category', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_monitors');
    }
};
