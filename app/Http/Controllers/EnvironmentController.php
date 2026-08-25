<?php

namespace App\Http\Controllers;

use App\Models\EnvironmentReading;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    /**
     * A reading older than this is treated as "sensor went offline" rather
     * than a live value, even though one was recorded at some point.
     */
    protected const STALE_AFTER_MINUTES = 10;

    /**
     * Latest server room temperature/humidity reading for the Dashboard's
     * gauge cards, plus the configured normal range. No device is wired up
     * yet, so `reading` is simply null until ingest() below starts
     * receiving data — the frontend renders that as "sensor not connected"
     * rather than an error.
     */
    public function latest(): JsonResponse
    {
        $reading = EnvironmentReading::query()->latest('recorded_at')->first();
        $settings = SystemSetting::current();

        return response()->json([
            'data' => [
                'reading' => $reading ? [
                    'temperature_c' => $reading->temperature_c,
                    'humidity_pct' => $reading->humidity_pct,
                    'recorded_at' => $reading->recorded_at->toIso8601String(),
                ] : null,
                'stale' => $reading
                    ? $reading->recorded_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))
                    : false,
                'thresholds' => [
                    'room_temp_min_c' => $settings->room_temp_min_c,
                    'room_temp_max_c' => $settings->room_temp_max_c,
                    'room_humidity_min_pct' => $settings->room_humidity_min_pct,
                    'room_humidity_max_pct' => $settings->room_humidity_max_pct,
                ],
            ],
        ]);
    }

    /**
     * Where the (not-yet-installed) room sensor will push its readings —
     * a plain shared-secret token rather than user auth, since a device
     * has no session/login. Set ENVIRONMENT_SENSOR_TOKEN once real
     * hardware exists; until then every request here is rejected, which
     * is the correct behavior (there's nothing legitimate to accept).
     */
    public function ingest(Request $request): JsonResponse
    {
        $configuredToken = config('services.environment_sensor.token');

        abort_if(blank($configuredToken), 503, 'Environment sensor ingestion is not configured yet.');

        $providedToken = (string) $request->header('X-Sensor-Token', $request->input('token', ''));

        abort_unless(hash_equals($configuredToken, $providedToken), 401);

        $validated = $request->validate([
            'temperature_c' => 'nullable|numeric|between:-40,100',
            'humidity_pct' => 'nullable|numeric|between:0,100',
            'source' => 'nullable|string|max:100',
        ]);

        abort_if(
            blank($validated['temperature_c'] ?? null) && blank($validated['humidity_pct'] ?? null),
            422,
            'At least one of temperature_c or humidity_pct is required.',
        );

        EnvironmentReading::create([
            ...$validated,
            'recorded_at' => now(),
        ]);

        return response()->json(['message' => 'ok'], 201);
    }
}
