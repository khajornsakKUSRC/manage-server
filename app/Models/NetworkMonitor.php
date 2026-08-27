<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMonitor extends Model
{
    public const CATEGORIES = [
        'wan' => 'WAN',
        'gateway' => 'Gateway',
        'services' => 'Services',
        'dns' => 'DNS',
        'switch' => 'Switch',
        'server' => 'Server',
    ];

    public const TYPES = [
        'ping' => 'Ping (ICMP)',
        'http' => 'HTTP(S)',
        'tcp' => 'TCP Port',
        'dns' => 'DNS Lookup',
    ];

    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'name',
        'category',
        'type',
        'target',
        'port',
        'interval_seconds',
        'timeout_ms',
        'is_active',
        'last_status',
        'last_checked_at',
        'last_response_time_ms',
        'last_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function checks()
    {
        return $this->hasMany(NetworkMonitorCheck::class);
    }
}
