<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vm extends Model
{
    protected $fillable = [
        'host_id',
        'ip',
        'name',
        'dns',
        'state',
        'provisioned_space',
        'used_space',
        'memory_gb',
        'cpu_cores',
    ];

    public function host()
    {
        return $this->belongsTo(Host::class);
    }
}
