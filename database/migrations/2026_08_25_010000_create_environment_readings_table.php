<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_readings', function (Blueprint $table) {
            $table->id();

            $table->float('temperature_c')->nullable();
            $table->float('humidity_pct')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('recorded_at');

            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_readings');
    }
};
