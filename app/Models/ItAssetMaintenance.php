<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAssetMaintenance extends Model
{
    public const TYPES = [
        'repair' => 'ซ่อม',
        'maintenance' => 'บำรุงรักษา',
    ];

    public const STATUSES = [
        'open' => 'รอดำเนินการ',
        'in_progress' => 'กำลังดำเนินการ',
        'done' => 'เสร็จสิ้น',
    ];

    protected $fillable = [
        'it_asset_id',
        'type',
        'title',
        'description',
        'vendor',
        'cost',
        'performed_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'cost' => 'decimal:2',
    ];

    /** @return BelongsTo<ItAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
