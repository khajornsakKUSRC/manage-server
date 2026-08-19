<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportItem extends Model
{
    protected $fillable = [
        'daily_report_id',
        'name',
        'host',
        'dns',
        'state',
        'status',
        'provisioned_space',
        'used_space',
        'host_cpu',
        'host_mem',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }
}
