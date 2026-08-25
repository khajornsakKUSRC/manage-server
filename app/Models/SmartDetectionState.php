<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmartDetectionState extends Model
{
    protected $table = 'smart_detection_state';

    protected $fillable = [
        'vm_id',
        'category',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function vm()
    {
        return $this->belongsTo(Vm::class);
    }
}
