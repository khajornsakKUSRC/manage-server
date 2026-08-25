<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('maintenance_mode_enabled')->default(false);
            $table->text('maintenance_message')->nullable();

            $table->string('favicon_path')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('footer_text')->nullable();

            $table->unsignedTinyInteger('cpu_warning_pct')->default(70);
            $table->unsignedTinyInteger('cpu_critical_pct')->default(85);
            $table->unsignedTinyInteger('mem_warning_pct')->default(70);
            $table->unsignedTinyInteger('mem_critical_pct')->default(85);
            $table->unsignedTinyInteger('datastore_warning_pct')->default(70);
            $table->unsignedTinyInteger('datastore_critical_pct')->default(85);

            $table->unsignedInteger('session_timeout_minutes')->default(120);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
