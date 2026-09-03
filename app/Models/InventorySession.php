<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An asset-counting round. Progress (counted / total, by status) is always
 * derived from inventory_session_items — nothing here is hand-updated.
 */
class InventorySession extends Model
{
    public const STATUSES = [
        'open' => 'กำลังตรวจนับ',
        'closed' => 'ปิดรอบแล้ว',
    ];

    protected $fillable = [
        'name',
        'status',
        'scope_category_id',
        'scope_location',
        'note',
        'started_by',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<InventorySessionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventorySessionItem::class);
    }

    /** @return BelongsTo<ItAssetCategory, $this> */
    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(ItAssetCategory::class, 'scope_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
