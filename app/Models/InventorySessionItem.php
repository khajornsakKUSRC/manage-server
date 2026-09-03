<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySessionItem extends Model
{
    protected $fillable = [
        'inventory_session_id',
        'it_asset_id',
        'it_asset_inspection_id',
        'counted',
        'status',
        'counted_by',
        'counted_by_name',
        'counted_at',
    ];

    protected $casts = [
        'counted' => 'boolean',
        'counted_at' => 'datetime',
    ];

    /** @return BelongsTo<InventorySession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    /** @return BelongsTo<ItAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /** @return BelongsTo<ItAssetInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ItAssetInspection::class, 'it_asset_inspection_id');
    }
}
