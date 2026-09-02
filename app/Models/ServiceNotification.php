<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceNotification extends Model
{
    protected $fillable = [
        'monitored_service_id',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];
}
