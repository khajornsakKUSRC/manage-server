<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItRepairRequest extends Model
{
    /**
     * Repair lifecycle, keyed by the value stored in `status`.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'resolved' => 'Resolved',
        // Terminal: set automatically once the recipient submits their
        // rating from the public tracker; the rating is then locked.
        'closed' => 'Job Closed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'recipient_email',
        'full_name',
        'contact_number',
        'requested_at',
        'service_type',
        'provider_name',
        'details',
        'status',
        'created_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(ItRepairEvaluation::class);
    }
}
