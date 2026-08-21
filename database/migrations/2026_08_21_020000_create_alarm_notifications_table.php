<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('alarm_key')->unique();
            $table->string('object_type');
            $table->string('object_name');
            $table->string('alarm_name');
            $table->timestamp('notified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_notifications');
    }
};
