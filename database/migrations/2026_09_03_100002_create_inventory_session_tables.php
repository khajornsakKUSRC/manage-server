<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online asset-counting rounds ("รอบตรวจนับครุภัณฑ์"). Opening a round
     * snapshots every in-scope asset into inventory_session_items; each
     * scan/verify flips one item to counted and links the inspection it
     * produced, so progress is derived, never hand-tracked.
     */
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('open');    // open | closed
            $table->foreignId('scope_category_id')->nullable()->constrained('it_asset_categories')->nullOnDelete();
            $table->string('scope_location')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('inventory_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('it_asset_inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('counted')->default(false);
            $table->string('status', 20)->nullable();          // mirror of the inspection's status
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('counted_by_name')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_session_id', 'it_asset_id']);
            $table->index(['inventory_session_id', 'counted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_session_items');
        Schema::dropIfExists('inventory_sessions');
    }
};
