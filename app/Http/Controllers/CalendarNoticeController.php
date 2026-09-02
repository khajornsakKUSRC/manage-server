<?php

namespace App\Http\Controllers;

use App\Models\CalendarNotice;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarNoticeController extends Controller
{
    public function index(): Response
    {
        $notices = CalendarNotice::with('createdBy:id,name')
            ->orderBy('notice_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarNotice $notice) => $this->present($notice));

        return Inertia::render('calendar-notice/index', [
            'notices' => $notices,
            'types' => CalendarNotice::TYPES,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);

        $notice = CalendarNotice::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Created calendar notice '{$notice->title}' for {$notice->notice_date->toDateString()}",
            subjectType: 'calendar_notice',
            subjectLabel: $notice->title,
        );

        return back()->with('success', 'Calendar notice added successfully.');
    }

    public function update(Request $request, CalendarNotice $calendarNotice, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);

        $calendarNotice->update($validated);

        // Re-arm the Telegram reminder when the time was changed (or newly
        // set) — otherwise a notice that already fired would stay silent
        // on its new schedule.
        if ($calendarNotice->wasChanged('remind_at')) {
            $calendarNotice->forceFill(['reminded_at' => null])->save();
        }

        $activityLogger->record(
            action: 'updated',
            description: "Updated calendar notice '{$calendarNotice->title}'",
            subjectType: 'calendar_notice',
            subjectLabel: $calendarNotice->title,
        );

        return back()->with('success', 'Calendar notice updated successfully.');
    }

    public function destroy(CalendarNotice $calendarNotice, ActivityLogger $activityLogger): RedirectResponse
    {
        $title = $calendarNotice->title;

        $calendarNotice->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted calendar notice '{$title}'",
            subjectType: 'calendar_notice',
            subjectLabel: $title,
        );

        return back()->with('success', 'Calendar notice removed successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        // An untouched <input type="datetime-local"> posts '' rather than
        // null, which would fail the `date` rule even with `nullable` —
        // treat any empty reminder value as "not set".
        $request->merge([
            'remind_at' => $request->filled('remind_at') ? $request->input('remind_at') : null,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'notice_date' => 'required|date',
            'remind_at' => 'nullable|date',
            'type' => ['required', Rule::in(array_keys(CalendarNotice::TYPES))],
        ]);

        // Pin the reminder to the minute (the input has no seconds
        // anyway), so an unrelated edit doesn't look like a reminder-time
        // change and needlessly re-arm the Telegram reminder.
        if (! empty($validated['remind_at'])) {
            $validated['remind_at'] = Carbon::parse($validated['remind_at'])->startOfMinute()->toDateTimeString();
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CalendarNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'message' => $notice->message,
            'notice_date' => $notice->notice_date->toDateString(),
            'remind_at' => $notice->remind_at?->toIso8601String(),
            'reminded_at' => $notice->reminded_at?->toIso8601String(),
            'type' => $notice->type,
            'type_label' => CalendarNotice::TYPES[$notice->type] ?? $notice->type,
            'created_by' => $notice->createdBy?->name,
            'created_at' => $notice->created_at?->toIso8601String(),
        ];
    }
}
