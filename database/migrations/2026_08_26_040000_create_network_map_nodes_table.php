<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_map_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // The raw link as pasted by the user — kept verbatim so the
            // "Open in Google Maps" link always works even if we couldn't
            // parse coordinates out of it (see GoogleMapsLinkParser).
            $table->string('google_maps_url', 2048);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('ip_address');
            $table->boolean('is_active')->default(true);

            // Cached copy of the latest live ping — pinging happens on
            // demand (see NetworkMapController::ping), driven by the map
            // page polling every 20s while it's open, not a cron job (20s
            // is finer than Laravel's scheduler can go).
            $table->string('last_status')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_map_nodes');
    }
};
