<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_detection_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained()->cascadeOnDelete();
            $table->string('category');

            // Stable identifier for "this specific issue" within its
            // category on this VM (e.g. "port:8080", "service:mysqld",
            // "bruteforce:203.0.113.5") — lets a recurring scan update the
            // same row (bumping last_detected_at) instead of duplicating
            // it, and lets a resolved finding reopen if it recurs.
            $table->string('fingerprint');

            $table->string('severity');
            $table->string('title');
            $table->text('detail')->nullable();
            $table->string('status')->default('open');

            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['vm_id', 'category', 'fingerprint']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_detection_findings');
    }
};
