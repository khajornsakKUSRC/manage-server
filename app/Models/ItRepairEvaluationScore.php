<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItRepairEvaluationScore extends Model
{
    protected $fillable = [
        'it_repair_evaluation_id',
        'it_repair_eval_criterion_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ItRepairEvaluation::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ItRepairEvalCriterion::class, 'it_repair_eval_criterion_id');
    }
}
