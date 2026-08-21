<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmNotification extends Model
{
    protected $fillable = [
        'alarm_key',
        'object_type',
        'object_name',
        'alarm_name',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];
}
