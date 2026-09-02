<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItRepairEvaluation extends Model
{
    protected $fillable = [
        'it_repair_request_id',
        'evaluated_at',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'evaluated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItRepairRequest::class, 'it_repair_request_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ItRepairEvaluationScore::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
