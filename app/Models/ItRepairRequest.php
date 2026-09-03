<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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

    /**
     * Kept out of array/JSON output — the token is a credential and is only
     * ever handed back deliberately, in the tracking link and payload.
     *
     * @var list<string>
     */
    protected $hidden = [
        'public_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->public_token ??= Str::random(48);
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(ItRepairEvaluation::class);
    }
}
