<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItAssetCategory extends Model
{
    protected $fillable = ['name', 'code_prefix'];

    /** @return HasMany<ItAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(ItAsset::class);
    }
}
