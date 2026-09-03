<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rest of an asset's life: repairs & preventive maintenance, the
     * chain of who held it / where it lived, and the software + licences
     * installed on it. All hang off it_assets and are appended over time.
     */
    public function up(): void
    {
        Schema::create('it_asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('repair');   // repair | maintenance
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('performed_at');
            $table->string('status', 20)->default('done');    // open | in_progress | done
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['it_asset_id', 'performed_at']);
        });

        Schema::create('it_asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            $table->string('assignee_name');
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->date('assigned_at');
            $table->date('returned_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['it_asset_id', 'assigned_at']);
        });

        Schema::create('it_asset_software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_asset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('license_key')->nullable();
            $table->string('license_type', 20)->nullable();  // oem | volume | subscription | free
            $table->unsignedSmallInteger('seats')->nullable();
            $table->string('vendor')->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('it_asset_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_asset_software');
        Schema::dropIfExists('it_asset_assignments');
        Schema::dropIfExists('it_asset_maintenances');
    }
};
