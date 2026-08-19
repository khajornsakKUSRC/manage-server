<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('status')->nullable();
            $table->string('provisioned_space')->nullable();
            $table->string('used_space')->nullable();
            $table->string('host_cpu')->nullable();
            $table->string('host_mem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_items');
    }
};
