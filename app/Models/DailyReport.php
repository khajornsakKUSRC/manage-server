<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    public const INCIDENTS = [
        'network' => 'Network ขัดข้อง',
        'power' => 'ไฟฟ้าดับ',
        'hardware' => 'Hardware เสีย',
        'disk_full' => 'พื้นที่ดิสก์เต็ม',
        'performance' => 'ประสิทธิภาพลดลง',
        'application' => 'แอปพลิเคชันขัดข้อง',
        'security' => 'ปัญหาด้านความปลอดภัย',
        'other' => 'อื่นๆ',
    ];

    public const ACTIONS = [
        'shutdown' => 'Shutdown',
        'restart' => 'Restart',
    ];

    protected $fillable = [
        'report_date',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(DailyReportItem::class);
    }

    public function incidents()
    {
        return $this->hasMany(DailyReportIncident::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
