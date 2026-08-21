<?php

namespace App\Http\Controllers;

use App\Models\Vm;
use App\Services\ModSecurityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ModSecurityController extends Controller
{
    protected const DEFAULT_LIMIT = 5;

    protected const FILTERED_LIMIT = 50;

    public function index(): Response
    {
        return Inertia::render('modsecurity/index', [
            'vms' => Vm::active()
                ->whereNotNull('ip')
                ->where('ip', '!=', '')
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    /**
     * Fetches ModSecurity audit log errors from the given VM over SSH. With
     * no date range, returns the last 5 errors; with one, returns every
     * error within it (capped at 50).
     */
    public function logs(Request $request, ModSecurityLogService $logService): JsonResponse
    {
        $validated = $request->validate([
            'vm' => 'required|string',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        $vm = Vm::active()->where('name', $validated['vm'])->first();

        if (! $vm || ! $vm->ip) {
            return response()->json(['message' => 'ไม่พบ VM นี้ หรือ VM ไม่มี IP address ที่บันทึกไว้'], 404);
        }

        try {
            $transactions = $logService->fetch($vm->ip);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: 'ไม่สามารถอ่าน log จาก VM ได้'], 502);
        }

        $hasRange = ! empty($validated['from']) || ! empty($validated['to']);

        if ($hasRange) {
            $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
            $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;

            $transactions = array_values(array_filter(
                $transactions,
                fn (array $t) => (! $from || $t['time']->gte($from)) && (! $to || $t['time']->lte($to)),
            ));

            $transactions = array_slice($transactions, 0, self::FILTERED_LIMIT);
        } else {
            $transactions = array_slice($transactions, 0, self::DEFAULT_LIMIT);
        }

        return response()->json([
            'data' => array_map(fn (array $t) => [
                'id' => $t['id'],
                'time' => $t['time']->toIso8601String(),
                'source_ip' => $t['source_ip'],
                'request' => $t['request'],
                'messages' => $t['messages'],
            ], $transactions),
        ]);
    }
}
