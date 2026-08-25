<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvironmentReading extends Model
{
    protected $fillable = [
        'temperature_c',
        'humidity_pct',
        'source',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature_c' => 'float',
            'humidity_pct' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
