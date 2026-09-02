<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');

            // The day the notice is about — what it's pinned to on the
            // calendar grid.
            $table->date('notice_date');

            // Optional "remind me on" date, typically ahead of notice_date.
            $table->date('remind_at')->nullable();

            // server | network | website — see CalendarNotice::TYPES.
            $table->string('type');

            // Who filed it. Kept even if the user is later removed, so the
            // notice doesn't vanish — just shows no author.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('notice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_notices');
    }
};
