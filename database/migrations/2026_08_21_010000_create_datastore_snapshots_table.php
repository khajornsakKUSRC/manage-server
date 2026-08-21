<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datastore_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('capacity');
            $table->unsignedBigInteger('free_space');
            $table->date('snapshot_date');
            $table->timestamps();

            $table->unique(['name', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datastore_snapshots');
    }
};
