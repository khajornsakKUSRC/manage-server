<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatastoreSnapshot extends Model
{
    protected $fillable = [
        'name',
        'type',
        'capacity',
        'free_space',
        'snapshot_date',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'free_space' => 'integer',
        'snapshot_date' => 'date',
    ];
}
