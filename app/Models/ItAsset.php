<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The asset master — entered once. Everything that happens to the asset
 * afterwards (inspections, repairs, hand-overs, software) is an appended
 * history row on a related table, never an edit here.
 *
 * @property string $public_token
 */
class ItAsset extends Model
{
    use SoftDeletes;

    /** status => Thai label */
    public const STATUSES = [
        'in_use' => 'ใช้งาน',
        'in_storage' => 'เก็บสำรอง',
        'repair' => 'ส่งซ่อม',
        'retired' => 'จำหน่ายแล้ว',
        'lost' => 'สูญหาย',
    ];

    protected $fillable = [
        'asset_code',
        'name',
        'it_asset_category_id',
        'brand',
        'model',
        'serial_number',
        'status',
        'department',
        'location',
        'assigned_to',
        'purchased_at',
        'price',
        'warranty_until',
        'photo_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'warranty_until' => 'date',
        'last_inspected_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    protected $hidden = ['public_token'];

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->public_token ??= Str::random(48);
        });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** @return BelongsTo<ItAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItAssetCategory::class, 'it_asset_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ItAssetInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(ItAssetInspection::class)->latest();
    }

    /** @return HasMany<ItAssetMaintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(ItAssetMaintenance::class)->latest('performed_at');
    }

    /** @return HasMany<ItAssetAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ItAssetAssignment::class)->latest('assigned_at');
    }

    /** @return HasMany<ItAssetSoftware, $this> */
    public function software(): HasMany
    {
        return $this->hasMany(ItAssetSoftware::class)->orderBy('name');
    }
}
