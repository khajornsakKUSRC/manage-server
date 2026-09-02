<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarNotice extends Model
{
    /**
     * Reminder types, keyed by the value stored in the `type` column.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'server' => 'Server',
        'network' => 'Network',
        'website' => 'Website',
    ];

    protected $fillable = [
        'title',
        'message',
        'notice_date',
        'remind_at',
        'type',
        'created_by',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'remind_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    /**
     * Notices whose reminder time has arrived and that haven't had their
     * Telegram reminder sent yet — see calendar-notices:notify.
     */
    public function scopeDueForReminder(Builder $query): Builder
    {
        return $query->whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->where('remind_at', '<=', now());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
