<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Records one row in the Activity Log. Captures the acting user and
     * their request IP automatically — pass $userId explicitly only for
     * events like login, where the acting user isn't yet the "current"
     * authenticated user at the point this fires.
     */
    public function record(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?string $subjectLabel = null,
        ?int $userId = null,
    ): void {
        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_label' => $subjectLabel,
            'description' => $description,
            'ip_address' => request()?->ip(),
        ]);
    }
}
