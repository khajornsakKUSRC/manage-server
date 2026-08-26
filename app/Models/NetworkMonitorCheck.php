<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMonitorCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'network_monitor_id',
        'status',
        'response_time_ms',
        'message',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function monitor()
    {
        return $this->belongsTo(NetworkMonitor::class, 'network_monitor_id');
    }
}
