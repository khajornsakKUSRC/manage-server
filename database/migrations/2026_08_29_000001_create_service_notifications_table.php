<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dedup table for the Services page's down-alerts — same shape and
        // purpose as alarm_notifications, kept separate rather than shared
        // since it's keyed by monitored_service_id, not an arbitrary string
        // key, and cleaning up (see ServiceNotificationService) is simpler
        // without needing a key-prefix convention to avoid colliding with
        // unrelated notification types in the same table.
        Schema::create('service_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_service_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('notified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_notifications');
    }
};
