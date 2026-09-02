<?php

namespace App\Http\Controllers;

use App\Models\ItRepairEvalCriterion;
use App\Models\ItRepairEvaluationScore;
use App\Models\ItRepairServiceType;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceEvaluationController extends Controller
{
    /** @var array<int, string> */
    private const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public function index(Request $request): Response
    {
        [$year, $month, $type] = $this->filters($request);
        $summary = $this->summarize($year, $month, $type);

        return Inertia::render('it-repair-evaluation/index', [
            'filters' => ['year' => $year, 'month' => $month, 'service_type' => $type],
            'availableYears' => $this->availableYears(),
            'serviceTypes' => ItRepairServiceType::orderBy('name')->pluck('name')->values(),
            'criteria' => ItRepairEvalCriterion::ordered()->get(['id', 'name', 'sort_order', 'is_active']),
            'summaryCriteria' => $summary['criteria']
                ->map(fn (ItRepairEvalCriterion $c) => ['id' => $c->id, 'name' => $c->name, 'is_active' => $c->is_active])
                ->values(),
            'rows' => $summary['rows'],
            'total' => $summary['total'],
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    /**
     * Renders the same summary as an on-screen HTML preview (?format=html,
     * used inside an <iframe>) or as a downloadable PDF.
     */
    public function export(Request $request): HttpResponse
    {
        [$year, $month, $type] = $this->filters($request);
        $summary = $this->summarize($year, $month, $type);

        $viewData = [
            'criteria' => $summary['criteria'],
            'rows' => $summary['rows'],
            'total' => $summary['total'],
            'year' => $year,
            'monthLabel' => $month ? self::THAI_MONTHS[$month] : 'ทั้งปี',
            'typeLabel' => $type ?: 'ทุกประเภท',
            'generatedAt' => now()->format('d/m/Y H:i'),
            'regularFontPath' => public_path('fonts/sarabun/Sarabun-Regular.ttf'),
            'boldFontPath' => public_path('fonts/sarabun/Sarabun-Bold.ttf'),
        ];

        if ($request->query('format') === 'html') {
            return response()->view('pdf.service-evaluation', $viewData);
        }

        $suffix = $month
            ? $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT)
            : (string) $year;

        return Pdf::loadView('pdf.service-evaluation', $viewData)
            ->setPaper('a4', 'landscape')
            ->download("service-evaluation-{$suffix}.pdf");
    }

    /**
     * @return array{0: int, 1: int|null, 2: string|null} [year, month, serviceType]
     */
    private function filters(Request $request): array
    {
        $year = $request->integer('year') ?: now()->year;

        $month = $request->integer('month');
        $month = ($month >= 1 && $month <= 12) ? $month : null;

        $type = trim((string) $request->query('service_type')) ?: null;

        return [$year, $month, $type];
    }

    private function availableYears(): Collection
    {
        return DB::table('it_repair_evaluations')
            ->selectRaw('DISTINCT YEAR(evaluated_at) as y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * The by-job-type rating breakdown for one year / optional month /
     * optional service type.
     *
     * @return array{criteria: Collection<int, ItRepairEvalCriterion>, rows: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    private function summarize(int $year, ?int $month, ?string $type): array
    {
        $scoreRows = $this->scopedScores($year, $month, $type)
            ->selectRaw('r.service_type as service_type, s.it_repair_eval_criterion_id as criterion_id, AVG(s.score) as avg_score, COUNT(*) as n')
            ->groupBy('r.service_type', 's.it_repair_eval_criterion_id')
            ->get();

        $evalCounts = DB::table('it_repair_evaluations as e')
            ->join('it_repair_requests as r', 'r.id', '=', 'e.it_repair_request_id')
            ->whereYear('e.evaluated_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('e.evaluated_at', $month))
            ->when($type, fn ($q) => $q->where('r.service_type', $type))
            ->selectRaw('r.service_type, COUNT(*) as n')
            ->groupBy('r.service_type')
            ->pluck('n', 'service_type');

        $criteriaWithData = $scoreRows->pluck('criterion_id')->unique();
        $criteria = ItRepairEvalCriterion::ordered()
            ->get(['id', 'name', 'is_active'])
            ->filter(fn (ItRepairEvalCriterion $c) => $c->is_active || $criteriaWithData->contains($c->id))
            ->values();

        $typeNames = $type
            ? collect([$type])
            : ItRepairServiceType::orderBy('name')->pluck('name')
                ->merge($scoreRows->pluck('service_type'))
                ->filter()
                ->unique()
                ->sort()
                ->values();

        $rows = $typeNames->map(function (string $name) use ($scoreRows, $evalCounts, $criteria) {
            $forType = $scoreRows->where('service_type', $name);

            return [
                'service_type' => $name,
                'evaluations' => (int) ($evalCounts[$name] ?? 0),
                'by_criterion' => $this->perCriterion($criteria, $forType),
                'overall' => $this->weightedMean($forType),
            ];
        })->all();

        return [
            'criteria' => $criteria,
            'rows' => $rows,
            'total' => [
                'by_criterion' => $this->perCriterion($criteria, $scoreRows),
                'overall' => $this->weightedMean($scoreRows),
                'evaluations' => (int) $evalCounts->sum(),
            ],
        ];
    }

    private function scopedScores(int $year, ?int $month, ?string $type): QueryBuilder
    {
        return DB::table('it_repair_evaluation_scores as s')
            ->join('it_repair_evaluations as e', 'e.id', '=', 's.it_repair_evaluation_id')
            ->join('it_repair_requests as r', 'r.id', '=', 'e.it_repair_request_id')
            ->whereYear('e.evaluated_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('e.evaluated_at', $month))
            ->when($type, fn ($q) => $q->where('r.service_type', $type));
    }

    /**
     * @param  Collection<int, ItRepairEvalCriterion>  $criteria
     * @return array<int, float|null>
     */
    private function perCriterion(Collection $criteria, Collection $scoreRows): array
    {
        $out = [];

        foreach ($criteria as $c) {
            $out[$c->id] = $this->weightedMean($scoreRows->where('criterion_id', $c->id));
        }

        return $out;
    }

    private function weightedMean(Collection $scoreRows): ?float
    {
        $n = $scoreRows->sum('n');

        if ($n <= 0) {
            return null;
        }

        return round($scoreRows->sum(fn ($r) => (float) $r->avg_score * $r->n) / $n, 2);
    }

    public function storeCriterion(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:it_repair_eval_criteria,name',
        ]);

        ItRepairEvalCriterion::create([
            'name' => $validated['name'],
            'sort_order' => (int) ItRepairEvalCriterion::max('sort_order') + 1,
            'is_active' => true,
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Added service evaluation criterion '{$validated['name']}'",
            subjectType: 'it_repair_eval_criterion',
            subjectLabel: $validated['name'],
        );

        return back()->with('success', 'Criterion added.');
    }

    public function updateCriterion(Request $request, ItRepairEvalCriterion $itRepairEvalCriterion, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('it_repair_eval_criteria', 'name')->ignore($itRepairEvalCriterion->id)],
            'sort_order' => 'required|integer|min:0|max:9999',
            'is_active' => 'boolean',
        ]);

        $itRepairEvalCriterion->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $activityLogger->record(
            action: 'updated',
            description: "Updated service evaluation criterion '{$itRepairEvalCriterion->name}'",
            subjectType: 'it_repair_eval_criterion',
            subjectLabel: $itRepairEvalCriterion->name,
        );

        return back()->with('success', 'Criterion updated.');
    }

    public function destroyCriterion(Request $request, ItRepairEvalCriterion $itRepairEvalCriterion, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManage($request);

        if (ItRepairEvaluationScore::where('it_repair_eval_criterion_id', $itRepairEvalCriterion->id)->exists()) {
            return back()->with('error', 'This criterion already has evaluation data — deactivate it instead of deleting.');
        }

        $name = $itRepairEvalCriterion->name;
        $itRepairEvalCriterion->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted service evaluation criterion '{$name}'",
            subjectType: 'it_repair_eval_criterion',
            subjectLabel: $name,
        );

        return back()->with('success', 'Criterion removed.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403);
    }
}
