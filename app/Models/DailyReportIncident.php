<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportIncident extends Model
{
    protected $fillable = [
        'daily_report_id',
        'vm_name',
        'incident',
        'action',
        'remark',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }
}
