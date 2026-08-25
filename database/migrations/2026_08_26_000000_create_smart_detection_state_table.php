<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per VM per detection category, holding whatever baseline
        // that category's detector needs to diff against on the next scan
        // (e.g. the set of process names already seen, the last processed
        // auth-log line). See SmartDetectionService.
        Schema::create('smart_detection_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->json('state')->nullable();
            $table->timestamps();

            $table->unique(['vm_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_detection_state');
    }
};
