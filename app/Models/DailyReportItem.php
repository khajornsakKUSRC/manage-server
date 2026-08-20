<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportItem extends Model
{
    protected $fillable = [
        'daily_report_id',
        'name',
        'host',
        'power_state',
        'cpu_count',
        'memory_gb',
        'disk_usage_pct',
        'uptime_seconds',
        'certificate_exp',
        'notes',
    ];

    protected $casts = [
        'cpu_count' => 'integer',
        'memory_gb' => 'float',
        'disk_usage_pct' => 'float',
        'uptime_seconds' => 'integer',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }
}
