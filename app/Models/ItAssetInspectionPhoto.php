<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItAssetInspectionPhoto extends Model
{
    protected $fillable = ['it_asset_inspection_id', 'path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** @return BelongsTo<ItAssetInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ItAssetInspection::class, 'it_asset_inspection_id');
    }
}
