<?php

namespace App\Http\Controllers;

use App\Mail\ItRepairNotificationMail;
use App\Models\ItRepairEvalCriterion;
use App\Models\ItRepairEvaluation;
use App\Models\ItRepairRequest;
use App\Models\ItRepairServiceType;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ItRepairController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = ItRepairRequest::with(['createdBy:id,name', 'evaluation.scores'])
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ItRepairRequest $r) => $this->present($r));

        return Inertia::render('it-repair/index', [
            'requests' => $requests,
            'serviceTypes' => ItRepairServiceType::orderBy('name')
                ->get(['id', 'name', 'provider_name']),
            'statuses' => ItRepairRequest::STATUSES,
            'criteria' => ItRepairEvalCriterion::active()->ordered()->get(['id', 'name']),
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    /**
     * The public, login-free request form (rendered without the app
     * shell — see the layout switch in resources/js/app.tsx).
     */
    public function create(Request $request): Response
    {
        // The "track your request" link carries the request's own secret
        // token (?t=...) so the person who filed it lands straight on their
        // status + rating, no email to type. The token is the credential.
        $token = trim((string) $request->query('t', ''));
        $linked = $token !== ''
            ? ItRepairRequest::with('evaluation.scores')->where('public_token', $token)->first()
            : null;

        return Inertia::render('it-repair/public', [
            'serviceTypes' => ItRepairServiceType::orderBy('name')->get(['id', 'name', 'provider_name']),
            'criteria' => ItRepairEvalCriterion::active()->ordered()->get(['id', 'name']),
            'submittedId' => $request->integer('submitted') ?: null,
            'trackToken' => $linked ? $token : null,
            'linkedRequest' => $linked ? $this->presentPublic($linked) : null,
        ]);
    }

    /**
     * Public status lookup — by the recipient's own email, or by the
     * request's secret token (from the tracking link). Either identifies
     * the person; neither requires an account.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required_without:token|nullable|email',
            'token' => 'required_without:email|nullable|string',
        ]);

        $query = ItRepairRequest::with('evaluation.scores')
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        if (! empty($validated['token'])) {
            $query->where('public_token', $validated['token']);
        } else {
            $query->whereRaw('LOWER(recipient_email) = ?', [mb_strtolower($validated['email'])]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (ItRepairRequest $r) => $this->presentPublic($r)),
        ]);
    }

    /**
     * The recipient rates a resolved request from the public tracker.
     * Guarded by a match on the request's own recipient email, and only
     * allowed once the request is resolved.
     */
    public function publicEvaluate(Request $request, ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required_without:token|nullable|email',
            'token' => 'required_without:email|nullable|string',
            'comment' => 'nullable|string|max:2000',
            'scores' => 'required|array|min:1',
            'scores.*' => 'required|integer|between:1,5',
        ]);

        $viaToken = ! empty($validated['token'])
            && hash_equals((string) $itRepairRequest->public_token, (string) $validated['token']);
        $viaEmail = ! empty($validated['email'])
            && mb_strtolower($validated['email']) === mb_strtolower($itRepairRequest->recipient_email);

        abort_unless(
            $viaToken || $viaEmail,
            403,
            'This link or email does not match the request.',
        );
        abort_if($itRepairRequest->status === 'closed', 422, 'This request is closed — the rating can no longer be changed.');
        abort_unless($itRepairRequest->status === 'resolved', 422, 'This request is not resolved yet.');

        $this->persistEvaluation($itRepairRequest, $validated['scores'], $validated['comment'] ?? null, null);

        // Submitting the rating closes the job — the status becomes
        // "Job Closed" and the recipient can no longer edit it.
        $itRepairRequest->update(['status' => 'closed']);

        $activityLogger->record(
            action: 'updated',
            description: "Recipient rated and closed IT repair request #{$itRepairRequest->id}",
            subjectType: 'it_repair_request',
            subjectLabel: $itRepairRequest->full_name,
        );

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validatedRequest($request);
        $validated['provider_name'] = $validated['provider_name'] ?: $this->resolveProvider($validated['service_type']);

        $repair = ItRepairRequest::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Filed IT repair request for {$repair->full_name} ({$repair->service_type})",
            subjectType: 'it_repair_request',
            subjectLabel: $repair->full_name,
        );

        return back()->with('success', 'Repair request submitted.');
    }

    /**
     * Anonymous submission from /it-repair/new. No status (staff set that
     * later), no created_by, provider resolved from the chosen type.
     */
    public function publicStore(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email|max:255',
            'full_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:50',
            'requested_at' => 'required|date',
            'service_type' => 'required|string|max:255',
            'details' => 'required|string|max:5000',
        ]);

        $repair = ItRepairRequest::create([
            ...$validated,
            'provider_name' => $this->resolveProvider($validated['service_type']),
            'status' => 'pending',
            'created_by' => null,
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Public IT repair request #{$repair->id} from {$repair->full_name} ({$repair->service_type})",
            subjectType: 'it_repair_request',
            subjectLabel: $repair->full_name,
        );

        return redirect()->route('it-repair.create', [
            'submitted' => $repair->id,
            't' => $repair->public_token,
        ]);
    }

    public function updateStatus(Request $request, ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ItRepairRequest::STATUSES))],
        ]);

        $itRepairRequest->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Set IT repair request #{$itRepairRequest->id} to '{$itRepairRequest->status}'",
            subjectType: 'it_repair_request',
            subjectLabel: $itRepairRequest->full_name,
        );

        return back()->with('success', 'Status updated.');
    }

    public function update(Request $request, ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validatedRequest($request);
        $validated['provider_name'] = $validated['provider_name'] ?: $this->resolveProvider($validated['service_type']);

        $itRepairRequest->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated IT repair request #{$itRepairRequest->id} ({$itRepairRequest->full_name})",
            subjectType: 'it_repair_request',
            subjectLabel: $itRepairRequest->full_name,
        );

        return back()->with('success', 'Repair request updated.');
    }

    /**
     * Sends the request's recipient the notification email whose
     * header/subject/details/footer are configured on Settings → IT Repair
     * Notification Email.
     */
    public function sendEmail(ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): RedirectResponse
    {
        try {
            Mail::to($itRepairRequest->recipient_email)->send(new ItRepairNotificationMail($itRepairRequest));
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Failed to send email: '.$e->getMessage()]);

            return back();
        }

        $activityLogger->record(
            action: 'updated',
            description: "Sent notification email for IT repair request #{$itRepairRequest->id} to {$itRepairRequest->recipient_email}",
            subjectType: 'it_repair_request',
            subjectLabel: $itRepairRequest->full_name,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email sent to '.$itRepairRequest->recipient_email.'.']);

        return back();
    }

    public function destroy(ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): RedirectResponse
    {
        $label = "#{$itRepairRequest->id} ({$itRepairRequest->full_name})";
        $itRepairRequest->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted IT repair request {$label}",
            subjectType: 'it_repair_request',
            subjectLabel: $itRepairRequest->full_name,
        );

        return back()->with('success', 'Repair request removed.');
    }

    /**
     * Record (or replace) the service evaluation for one repair request.
     */
    public function storeEvaluation(Request $request, ItRepairRequest $itRepairRequest, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'comment' => 'nullable|string|max:2000',
            'scores' => 'required|array|min:1',
            'scores.*' => 'required|integer|between:1,5',
        ]);

        $this->persistEvaluation($itRepairRequest, $validated['scores'], $validated['comment'] ?? null, $request->user()->id);

        $activityLogger->record(
            action: 'updated',
            description: "Recorded service evaluation for IT repair request #{$itRepairRequest->id}",
            subjectType: 'it_repair_evaluation',
            subjectLabel: $itRepairRequest->full_name,
        );

        return back()->with('success', 'Evaluation saved.');
    }

    public function storeType(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:it_repair_service_types,name',
            'provider_name' => 'nullable|string|max:255',
        ]);

        ItRepairServiceType::create($validated);

        $activityLogger->record(
            action: 'created',
            description: "Added IT repair service type '{$validated['name']}'",
            subjectType: 'it_repair_service_type',
            subjectLabel: $validated['name'],
        );

        return back()->with('success', 'Service type added.');
    }

    public function updateType(Request $request, ItRepairServiceType $itRepairServiceType, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('it_repair_service_types', 'name')->ignore($itRepairServiceType->id)],
            'provider_name' => 'nullable|string|max:255',
        ]);

        $itRepairServiceType->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated IT repair service type '{$itRepairServiceType->name}'",
            subjectType: 'it_repair_service_type',
            subjectLabel: $itRepairServiceType->name,
        );

        return back()->with('success', 'Service type updated.');
    }

    public function destroyType(Request $request, ItRepairServiceType $itRepairServiceType, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $name = $itRepairServiceType->name;
        $itRepairServiceType->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted IT repair service type '{$name}'",
            subjectType: 'it_repair_service_type',
            subjectLabel: $name,
        );

        return back()->with('success', 'Service type removed.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403);
    }

    private function resolveProvider(string $serviceType): ?string
    {
        return ItRepairServiceType::where('name', $serviceType)->value('provider_name');
    }

    /**
     * Upsert one request's evaluation from a {criterionId: score} map,
     * keeping only scores for currently-active criteria.
     *
     * @param  array<int|string, mixed>  $rawScores
     */
    private function persistEvaluation(ItRepairRequest $request, array $rawScores, ?string $comment, ?int $userId): void
    {
        $activeIds = ItRepairEvalCriterion::active()->pluck('id')->all();

        $scores = collect($rawScores)
            ->only($activeIds)
            ->map(fn ($score) => (int) $score);

        abort_if($scores->isEmpty(), 422, 'No valid criteria were scored.');

        $evaluation = ItRepairEvaluation::updateOrCreate(
            ['it_repair_request_id' => $request->id],
            ['evaluated_at' => now(), 'comment' => $comment, 'created_by' => $userId],
        );

        $evaluation->scores()->delete();
        $evaluation->scores()->createMany(
            $scores->map(fn (int $score, int $criterionId) => [
                'it_repair_eval_criterion_id' => $criterionId,
                'score' => $score,
            ])->values()->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'recipient_email' => 'required|email|max:255',
            'full_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:50',
            'requested_at' => 'required|date',
            'service_type' => 'required|string|max:255',
            'provider_name' => 'nullable|string|max:255',
            'details' => 'required|string|max:5000',
            'status' => ['required', Rule::in(array_keys(ItRepairRequest::STATUSES))],
        ]);
    }

    /**
     * The public tracker's view of one request — status, details and the
     * recipient's own rating. Shared by the token lookup on create() and
     * the email/token lookup in track(). Deliberately omits internal-only
     * fields (contact number, who filed it, the token itself).
     *
     * @return array<string, mixed>
     */
    private function presentPublic(ItRepairRequest $r): array
    {
        return [
            'id' => $r->id,
            'full_name' => $r->full_name,
            'requested_at' => $r->requested_at->toIso8601String(),
            'service_type' => $r->service_type,
            'provider_name' => $r->provider_name,
            'details' => $r->details,
            'status' => $r->status,
            'status_label' => ItRepairRequest::STATUSES[$r->status] ?? $r->status,
            'updated_at' => $r->updated_at?->toIso8601String(),
            'evaluation' => $r->evaluation ? [
                'evaluated_at' => $r->evaluation->evaluated_at->toIso8601String(),
                'comment' => $r->evaluation->comment,
                'scores' => $r->evaluation->scores
                    ->mapWithKeys(fn ($s) => [$s->it_repair_eval_criterion_id => $s->score]),
                'average' => round($r->evaluation->scores->avg('score') ?? 0, 2),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ItRepairRequest $r): array
    {
        $evaluation = $r->evaluation;

        return [
            'id' => $r->id,
            'recipient_email' => $r->recipient_email,
            'full_name' => $r->full_name,
            'contact_number' => $r->contact_number,
            'requested_at' => $r->requested_at->toIso8601String(),
            'service_type' => $r->service_type,
            'provider_name' => $r->provider_name,
            'details' => $r->details,
            'status' => $r->status,
            'status_label' => ItRepairRequest::STATUSES[$r->status] ?? $r->status,
            'created_by' => $r->createdBy?->name,
            'evaluation' => $evaluation ? [
                'evaluated_at' => $evaluation->evaluated_at->toIso8601String(),
                'comment' => $evaluation->comment,
                'scores' => $evaluation->scores
                    ->mapWithKeys(fn ($s) => [$s->it_repair_eval_criterion_id => $s->score]),
                'average' => round($evaluation->scores->avg('score') ?? 0, 2),
            ] : null,
        ];
    }
}
