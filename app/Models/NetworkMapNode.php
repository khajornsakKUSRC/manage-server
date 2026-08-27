<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMapNode extends Model
{
    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'name',
        'google_maps_url',
        'latitude',
        'longitude',
        'ip_address',
        'is_active',
        'last_status',
        'last_checked_at',
        'last_response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }
}
