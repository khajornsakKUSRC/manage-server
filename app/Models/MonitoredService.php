<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MonitoredService extends Model
{
    protected $fillable = [
        'label',
        'host',
        'service_name',
    ];

    protected $casts = [
        'last_healthy' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function notification(): HasOne
    {
        return $this->hasOne(ServiceNotification::class);
    }
}
