<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAssetSoftware extends Model
{
    protected $table = 'it_asset_software';

    public const LICENSE_TYPES = [
        'oem' => 'OEM',
        'volume' => 'Volume',
        'subscription' => 'Subscription',
        'free' => 'ฟรี / โอเพนซอร์ส',
    ];

    protected $fillable = [
        'it_asset_id',
        'name',
        'version',
        'license_key',
        'license_type',
        'seats',
        'vendor',
        'purchased_at',
        'expires_at',
        'note',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'seats' => 'integer',
    ];

    /** @return BelongsTo<ItAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }
}
