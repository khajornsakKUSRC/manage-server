<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmartDetectionFinding extends Model
{
    public const CATEGORIES = [
        'brute_force' => 'Brute Force Detection',
        'process' => 'Process Detection',
        'malware' => 'Malware Detection',
        'port_service' => 'Port/Service Detection',
        'service_failure' => 'Service Failure',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'vm_id',
        'category',
        'fingerprint',
        'severity',
        'title',
        'detail',
        'status',
        'first_detected_at',
        'last_detected_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function vm()
    {
        return $this->belongsTo(Vm::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
