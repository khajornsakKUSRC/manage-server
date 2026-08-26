<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('message')->nullable();
            $table->timestamp('checked_at');

            // Every history/uptime-bar query filters by monitor + a
            // checked_at range, so index on that pair rather than id.
            $table->index(['network_monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_monitor_checks');
    }
};
