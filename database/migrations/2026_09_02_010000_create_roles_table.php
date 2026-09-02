<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed roles describe *what a user manages* — Network, Server,
     * IP Phone, Developer, Computer, and so on. They're a descriptive label
     * only (shown on Manage Users); page access stays governed entirely by
     * users.permissions. A user with no roles is a "general user".
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#64748b');
            $table->timestamps();
        });

        // A starter set so the feature isn't empty on first open — matches
        // the examples in the brief. Admins can rename/remove these or add
        // their own from Manage Users → Manage Roles.
        $now = now();
        DB::table('roles')->insert(array_map(fn (array $r) => [
            'name' => $r[0],
            'description' => $r[1],
            'color' => $r[2],
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['Network', 'Switching, routing, firewalls and network links.', '#0ea5e9'],
            ['Server', 'Physical hosts, virtual machines and datastores.', '#8b5cf6'],
            ['IP Phone', 'VoIP handsets, extensions and the phone system.', '#f59e0b'],
            ['Developer', 'Application code, deployments and integrations.', '#22c55e'],
            ['Computer', 'End-user desktops, laptops and peripherals.', '#ef4444'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
