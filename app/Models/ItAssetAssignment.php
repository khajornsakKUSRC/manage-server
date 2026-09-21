<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAssetAssignment extends Model
{
    /** document_type => Thai label */
    public const DOCUMENT_TYPES = [
        'purchase' => 'ซื้อ',
        'hire' => 'จ้าง',
        'move' => 'ย้าย',
        'dispose' => 'จำหน่าย',
    ];

    protected $fillable = [
        'it_asset_id',
        'document_type',
        'document_number',
        'document_date',
        'assignee_name',
        'department',
        'location',
        'assigned_at',
        'returned_at',
        'note',
        'performed_by_name',
        'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'assigned_at' => 'date',
        'returned_at' => 'date',
    ];

    public function documentTypeLabel(): ?string
    {
        return $this->document_type ? (self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type) : null;
    }

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
