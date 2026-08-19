<?php

namespace Database\Seeders;

use App\Models\Host;
use App\Models\Vm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Vm::truncate();
        Host::truncate();
        Schema::enableForeignKeyConstraints();

        $host1 = Host::create(['name' => 'ESXi-Node-01', 'ip' => '192.168.1.10']);
        $host2 = Host::create(['name' => 'ESXi-Node-02', 'ip' => '192.168.1.11']);

        Vm::create([
            'host_id' => $host1->id,
            'ip' => '192.168.1.50',
            'name' => 'Web-Server-01',
            'state' => 'running',
            'provisioned_space' => '100 GB',
            'used_space' => '45 GB',
            'memory_gb' => 8,
            'cpu_cores' => 4,
        ]);

        Vm::create([
            'host_id' => $host1->id,
            'ip' => '192.168.1.51',
            'name' => 'DB-Server-01',
            'state' => 'running',
            'provisioned_space' => '250 GB',
            'used_space' => '120 GB',
            'memory_gb' => 16,
            'cpu_cores' => 8,
        ]);

        Vm::create([
            'host_id' => $host2->id,
            'ip' => '192.168.1.60',
            'name' => 'App-Server-01',
            'state' => 'stopped',
            'provisioned_space' => '50 GB',
            'used_space' => '20 GB',
            'memory_gb' => 4,
            'cpu_cores' => 2,
        ]);
    }
}
