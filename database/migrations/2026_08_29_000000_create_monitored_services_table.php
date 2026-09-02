<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_services', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('host');
            $table->string('service_name');
            $table->timestamps();

            // A given systemd unit only makes sense to monitor once per host
            // — this also protects against the same service being added
            // twice by accident.
            $table->unique(['host', 'service_name']);
        });

        // Seeds one real check so the Services page shows something on
        // first load instead of an empty state. Add/remove the rest from
        // the page itself (over SSH, same credentials as Smart Detection).
        DB::table('monitored_services')->insert([
            'label' => 'Postfix',
            'host' => '158.108.96.21',
            'service_name' => 'postfix',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_services');
    }
};
