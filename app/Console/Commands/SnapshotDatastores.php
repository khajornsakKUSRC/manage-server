<?php

namespace App\Console\Commands;

use App\Models\DatastoreSnapshot;
use App\Services\VsphereService;
use Illuminate\Console\Command;
use Throwable;

class SnapshotDatastores extends Command
{
    protected $signature = 'datastores:snapshot';

    protected $description = 'Records today\'s capacity/free-space for every vCenter datastore, for the Datastore page\'s fill-up projection';

    public function handle(VsphereService $vsphere): int
    {
        try {
            $datastores = $vsphere->getDatastores();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not reach vCenter: '.$e->getMessage());

            return self::FAILURE;
        }

        $today = now()->toDateString();

        foreach ($datastores as $datastore) {
            DatastoreSnapshot::updateOrCreate(
                ['name' => $datastore['name'], 'snapshot_date' => $today],
                [
                    'type' => $datastore['type'] ?? null,
                    'capacity' => $datastore['capacity'],
                    'free_space' => $datastore['free_space'],
                ],
            );
        }

        $this->info('Snapshotted '.count($datastores)." datastore(s) for {$today}.");

        return self::SUCCESS;
    }
}
