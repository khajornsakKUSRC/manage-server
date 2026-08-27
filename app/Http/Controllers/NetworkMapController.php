<?php

namespace App\Http\Controllers;

use App\Models\NetworkMapNode;
use App\Services\ActivityLogger;
use App\Services\GoogleMapsLinkParser;
use App\Services\ServerPingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NetworkMapController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('network-map/index');
    }

    /**
     * Every node's cached last-known status — the map page loads this on
     * mount, then calls ping() for each node itself to refresh live.
     */
    public function nodes(): JsonResponse
    {
        $nodes = NetworkMapNode::orderBy('name')->get()->map(fn (NetworkMapNode $node) => $this->present($node));

        return response()->json(['data' => $nodes]);
    }

    /**
     * Pings one node live (ICMP), caches the result on the node, and
     * returns it — called by the map page for every node every 20s, which
     * is finer-grained than Laravel's scheduler can go, so this is
     * client-driven rather than a cron job.
     */
    public function ping(NetworkMapNode $networkMapNode, ServerPingService $pingService): JsonResponse
    {
        $result = $pingService->ping($networkMapNode->ip_address);
        $checkedAt = now();

        $networkMapNode->update([
            'last_status' => $result['online'] ? NetworkMapNode::STATUS_UP : NetworkMapNode::STATUS_DOWN,
            'last_checked_at' => $checkedAt,
            'last_response_time_ms' => $result['online'] ? $result['response_time_ms'] ?? null : null,
        ]);

        return response()->json(['data' => $this->present($networkMapNode)]);
    }

    public function store(Request $request, GoogleMapsLinkParser $parser, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $location = $this->resolveLocation($validated, null, $parser);

        $node = NetworkMapNode::create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'is_active' => $validated['is_active'] ?? true,
            ...$location,
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Created map switch '{$node->name}'",
            subjectType: 'network_map_node',
            subjectLabel: $node->name,
        );

        return back()->with(
            'success',
            $location['latitude'] !== null
                ? 'Switch added successfully.'
                : "Switch added, but the location couldn't be determined from that link — it won't appear on the map until you edit it with a link that includes coordinates, or switch to entering latitude/longitude directly.",
        );
    }

    public function update(Request $request, NetworkMapNode $networkMapNode, GoogleMapsLinkParser $parser, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $location = $this->resolveLocation($validated, $networkMapNode, $parser);

        $networkMapNode->update([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'is_active' => $validated['is_active'] ?? true,
            ...$location,
        ]);

        $activityLogger->record(
            action: 'updated',
            description: "Updated map switch '{$networkMapNode->name}'",
            subjectType: 'network_map_node',
            subjectLabel: $networkMapNode->name,
        );

        return back()->with(
            'success',
            $location['latitude'] !== null
                ? 'Switch updated successfully.'
                : "Switch updated, but the location couldn't be determined from that link — it won't appear on the map until you edit it with a link that includes coordinates, or switch to entering latitude/longitude directly.",
        );
    }

    /**
     * Resolves {google_maps_url, latitude, longitude} from the validated
     * input, which arrives in one of two shapes depending on
     * location_mode:
     *  - "coordinates": latitude/longitude were typed in directly — used
     *    as-is, with a plain Google Maps query link generated from them.
     *  - "link": a Google Maps link was pasted — parsed for coordinates
     *    (re-parsed only if the link actually changed, since that may
     *    involve a short-link redirect over the network).
     *
     * @return array{google_maps_url: string, latitude: ?float, longitude: ?float}
     */
    private function resolveLocation(array $validated, ?NetworkMapNode $existing, GoogleMapsLinkParser $parser): array
    {
        if ($validated['location_mode'] === 'coordinates') {
            $lat = (float) $validated['latitude'];
            $lng = (float) $validated['longitude'];

            return [
                'google_maps_url' => "https://www.google.com/maps?q={$lat},{$lng}",
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        $url = $validated['google_maps_url'];
        $coords = ($existing && $existing->google_maps_url === $url)
            ? ['lat' => $existing->latitude, 'lng' => $existing->longitude]
            : $parser->parse($url);

        return [
            'google_maps_url' => $url,
            'latitude' => $coords['lat'] ?? null,
            'longitude' => $coords['lng'] ?? null,
        ];
    }

    public function destroy(NetworkMapNode $networkMapNode, ActivityLogger $activityLogger): RedirectResponse
    {
        $name = $networkMapNode->name;

        $networkMapNode->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted map switch '{$name}'",
            subjectType: 'network_map_node',
            subjectLabel: $name,
        );

        return back()->with('success', 'Switch deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'location_mode' => ['required', Rule::in(['link', 'coordinates'])],
            'google_maps_url' => 'required_if:location_mode,link|nullable|string|max:2048',
            'latitude' => 'required_if:location_mode,coordinates|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:location_mode,coordinates|nullable|numeric|between:-180,180',
            'ip_address' => 'required|string|max:45',
            'is_active' => 'boolean',
        ]);
    }

    private function present(NetworkMapNode $node): array
    {
        return [
            'id' => $node->id,
            'name' => $node->name,
            'google_maps_url' => $node->google_maps_url,
            'latitude' => $node->latitude,
            'longitude' => $node->longitude,
            'ip_address' => $node->ip_address,
            'is_active' => $node->is_active,
            'status' => $node->is_active ? ($node->last_status ?? NetworkMapNode::STATUS_PENDING) : 'paused',
            'last_checked_at' => $node->last_checked_at?->toIso8601String(),
            'last_response_time_ms' => $node->last_response_time_ms,
        ];
    }
}
