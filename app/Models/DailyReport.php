<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'original_filename',
        'imported_by',
        'inspector',
        'summary',
        'checklist',
        'overall_normal',
        'reason_text',
        'source',
    ];

    protected $casts = [
        'report_date' => 'date',
        'checklist' => 'array',
        'overall_normal' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(DailyReportItem::class);
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
