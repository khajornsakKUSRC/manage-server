<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'uptime_seconds',
        'update_status',
        'notes',
        'certificate_exp',
        'certificate_notified_exp',
        'certificate_notify_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'uptime_seconds' => 'integer',
            'certificate_notify_days' => 'integer',
        ];
    }

    public function host()
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * VMs currently in service — the ones that should count toward the
     * daily summary. Decommissioned/retired VMs are kept for record but
     * excluded via this scope.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
