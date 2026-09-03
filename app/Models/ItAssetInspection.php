<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One check of one asset — from staff, the login-free public page, or a
 * counting round. Immutable once written; the asset's own row keeps a
 * denormalised copy of the latest one for fast listing.
 */
class ItAssetInspection extends Model
{
    /** status => Thai label (the four choices offered on scan) */
    public const STATUSES = [
        'normal' => 'พบ/ปกติ',
        'damaged' => 'ชำรุด',
        'moved' => 'ย้าย',
        'missing' => 'ไม่พบ',
    ];

    public const SOURCES = ['staff', 'public', 'counting'];

    protected $fillable = [
        'it_asset_id',
        'inventory_session_id',
        'status',
        'note',
        'latitude',
        'longitude',
        'source',
        'inspected_by',
        'inspector_name',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** @return BelongsTo<ItAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /** @return HasMany<ItAssetInspectionPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ItAssetInspectionPhoto::class);
    }
}
