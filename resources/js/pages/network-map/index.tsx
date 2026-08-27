import { Head, router, useForm } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { AlertCircle, MapPin, Plus, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppearance } from '@/hooks/use-appearance';

type NodeStatus = 'up' | 'down' | 'pending' | 'paused';

interface MapNode {
    id: number;
    name: string;
    google_maps_url: string;
    latitude: number | null;
    longitude: number | null;
    ip_address: string;
    is_active: boolean;
    status: NodeStatus;
    last_checked_at: string | null;
    last_response_time_ms: number | null;
}

const PING_POLL_MS = 20_000;

// Default view (centered roughly on Thailand) shown until we know where any
// switch actually is — swapped for a bounds-fit over the real markers as
// soon as at least one has coordinates.
const DEFAULT_CENTER: [number, number] = [13.7563, 100.5018];
const DEFAULT_ZOOM = 6;

const MARKER_COLOR: Record<NodeStatus, string> = {
    up: '#22c55e',
    down: '#ef4444',
    pending: '#eab308',
    paused: '#9ca3af',
};

function statusBadgeClass(status: NodeStatus): string {
    switch (status) {
        case 'up':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'down':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'paused':
            return 'bg-gray-100 text-gray-600 dark:bg-gray-800/60 dark:text-gray-400';
        default:
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    }
}

function statusLabel(status: NodeStatus): string {
    switch (status) {
        case 'up':
            return 'Up';
        case 'down':
            return 'Down';
        case 'paused':
            return 'Paused';
        default:
            return 'Pending';
    }
}

function formatTime(iso: string | null): string {
    if (!iso) {
        return 'never';
    }

    return new Date(iso).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function markerIcon(status: NodeStatus): L.DivIcon {
    const color = MARKER_COLOR[status];
    const ring =
        status === 'down'
            ? `<span class="absolute inset-0 -m-1 animate-ping rounded-full" style="background-color:${color}44"></span>`
            : '';

    return L.divIcon({
        className: '',
        html: `<div class="relative">${ring}<div style="width:16px;height:16px;border-radius:9999px;background-color:${color};border:2px solid white;box-shadow:0 0 0 1px rgba(0,0,0,0.25)"></div></div>`,
        iconSize: [16, 16],
        iconAnchor: [8, 8],
        popupAnchor: [0, -10],
    });
}

// Builds popup content via DOM nodes (textContent, never innerHTML) so a
// switch name containing HTML can't inject markup into the map.
function buildPopupContent(node: MapNode): HTMLElement {
    const container = document.createElement('div');
    container.className = 'min-w-[180px] space-y-1 text-sm';

    const title = document.createElement('p');
    title.className = 'font-semibold';
    title.textContent = node.name;
    container.appendChild(title);

    const statusLine = document.createElement('p');
    statusLine.textContent =
        statusLabel(node.status) +
        (node.last_response_time_ms !== null
            ? ` — ${node.last_response_time_ms} ms`
            : '');
    statusLine.style.color = MARKER_COLOR[node.status];
    statusLine.className = 'font-medium';
    container.appendChild(statusLine);

    const ipLine = document.createElement('p');
    ipLine.className = 'text-muted-foreground';
    ipLine.textContent = node.ip_address;
    container.appendChild(ipLine);

    const timeLine = document.createElement('p');
    timeLine.className = 'text-xs text-muted-foreground';
    timeLine.textContent = `Checked ${formatTime(node.last_checked_at)}`;
    container.appendChild(timeLine);

    const link = document.createElement('a');
    link.href = node.google_maps_url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'text-xs text-blue-600 underline dark:text-blue-400';
    link.textContent = 'Open in Google Maps';
    container.appendChild(link);

    return container;
}

function SwitchMap({ nodes }: { nodes: MapNode[] }) {
    const { resolvedAppearance } = useAppearance();
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const tileLayerRef = useRef<L.TileLayer | null>(null);
    const markersRef = useRef<Map<number, L.Marker>>(new Map());
    const hasFitBoundsRef = useRef(false);

    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        mapRef.current = L.map(containerRef.current, {
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
        });

        return () => {
            mapRef.current?.remove();
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        tileLayerRef.current?.remove();

        // CARTO's free basemaps (no API key required) — matches the app's
        // light/dark theme instead of always showing a bright map.
        const url =
            resolvedAppearance === 'dark'
                ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

        tileLayerRef.current = L.tileLayer(url, {
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 19,
        }).addTo(map);
    }, [resolvedAppearance]);

    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        const markers = markersRef.current;
        const placed = nodes.filter(
            (n) => n.latitude !== null && n.longitude !== null,
        );
        const placedIds = new Set(placed.map((n) => n.id));

        for (const [id, marker] of markers) {
            if (!placedIds.has(id)) {
                marker.remove();
                markers.delete(id);
            }
        }

        for (const node of placed) {
            const latLng: L.LatLngExpression = [
                node.latitude as number,
                node.longitude as number,
            ];
            const existing = markers.get(node.id);

            if (existing) {
                existing.setLatLng(latLng);
                existing.setIcon(markerIcon(node.status));
                existing.setPopupContent(buildPopupContent(node));
            } else {
                const marker = L.marker(latLng, {
                    icon: markerIcon(node.status),
                }).addTo(map);
                marker.bindPopup(buildPopupContent(node));
                markers.set(node.id, marker);
            }
        }

        if (!hasFitBoundsRef.current && placed.length > 0) {
            hasFitBoundsRef.current = true;
            const bounds = L.latLngBounds(
                placed.map(
                    (n) => [n.latitude, n.longitude] as [number, number],
                ),
            );
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
        }
    }, [nodes]);

    // `isolate` pins Leaflet's internal z-index:1000 controls (zoom,
    // attribution) inside this element's own stacking context — without it
    // they'd otherwise render above the Add/Edit Switch dialog (z-50),
    // since Leaflet's control z-index outranks the dialog's.
    return (
        <div
            ref={containerRef}
            className="isolate h-[420px] w-full rounded-lg"
        />
    );
}

type LocationMode = 'link' | 'coordinates';

interface FormState {
    name: string;
    location_mode: LocationMode;
    google_maps_url: string;
    latitude: string;
    longitude: string;
    ip_address: string;
    is_active: boolean;
}

const EMPTY_FORM: FormState = {
    name: '',
    location_mode: 'link',
    google_maps_url: '',
    latitude: '',
    longitude: '',
    ip_address: '',
    is_active: true,
};

export default function Index() {
    const [nodes, setNodes] = useState<MapNode[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const nodesRef = useRef<MapNode[]>([]);

    const {
        data,
        setData,
        post,
        put,
        transform,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<FormState>(EMPTY_FORM);

    useEffect(() => {
        nodesRef.current = nodes;
    }, [nodes]);

    const fetchNodes = useCallback(async (): Promise<MapNode[]> => {
        const res = await fetch('/api/network-map/nodes');

        if (!res.ok) {
            throw new Error('request failed');
        }

        const json = await res.json();

        return Array.isArray(json.data) ? json.data : [];
    }, []);

    const pingAllActive = useCallback(async () => {
        const active = nodesRef.current.filter((n) => n.is_active);

        if (active.length === 0) {
            return;
        }

        const results = await Promise.all(
            active.map(async (node) => {
                try {
                    const res = await fetch(
                        `/api/network-map/nodes/${node.id}/ping`,
                    );

                    if (!res.ok) {
                        return null;
                    }

                    const json = await res.json();

                    return json.data as MapNode;
                } catch {
                    return null;
                }
            }),
        );

        setNodes((prev) =>
            prev.map((n) => results.find((r) => r?.id === n.id) ?? n),
        );
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setNodes(await fetchNodes());
        } catch {
            setError('ไม่สามารถโหลดข้อมูล Map Network ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchNodes]);

    useEffect(() => {
        let cancelled = false;

        fetchNodes()
            .then((data) => {
                if (!cancelled) {
                    setNodes(data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('ไม่สามารถโหลดข้อมูล Map Network ได้');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [fetchNodes]);

    // Pings every active switch every 20s while this page is open — finer
    // grained than Laravel's scheduler can go, so it's driven from here
    // rather than a cron job. The initial fetchNodes() effect above seeds
    // nodesRef first; this effect's own first tick fires immediately too.
    useEffect(() => {
        pingAllActive();
        const interval = setInterval(pingAllActive, PING_POLL_MS);

        return () => clearInterval(interval);
    }, [pingAllActive]);

    const openCreate = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setData(EMPTY_FORM);
        setDialogOpen(true);
    };

    const openEdit = (node: MapNode) => {
        setEditingId(node.id);
        clearErrors();
        setData({
            name: node.name,
            location_mode: 'link',
            google_maps_url: node.google_maps_url,
            latitude: node.latitude !== null ? String(node.latitude) : '',
            longitude: node.longitude !== null ? String(node.longitude) : '',
            ip_address: node.ip_address,
            is_active: node.is_active,
        });
        setDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Only one of the two location fields is relevant per mode — an
        // empty string in the other would fail the backend's `numeric` (or
        // just be noise for `string`) rule, so blank it out to null instead.
        transform((formData) => ({
            ...formData,
            google_maps_url:
                formData.location_mode === 'link'
                    ? formData.google_maps_url
                    : null,
            latitude:
                formData.location_mode === 'coordinates'
                    ? formData.latitude
                    : null,
            longitude:
                formData.location_mode === 'coordinates'
                    ? formData.longitude
                    : null,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reload().then(pingAllActive);
            },
        };

        if (editingId) {
            put(`/network-map/${editingId}`, options);
        } else {
            post('/network-map', options);
        }
    };

    const handleDelete = (node: MapNode) => {
        if (window.confirm(`Are you sure you want to delete "${node.name}"?`)) {
            router.delete(`/network-map/${node.id}`, { preserveScroll: true });
        }
    };

    const placedCount = useMemo(
        () => nodes.filter((n) => n.latitude !== null).length,
        [nodes],
    );
    const downCount = nodes.filter((n) => n.status === 'down').length;

    return (
        <>
            <Head title="Map Network" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-2 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Map Network ({nodes.length})
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {downCount > 0
                                ? `${downCount} switch(es) currently down`
                                : 'All switches are up'}
                            {nodes.length > placedCount &&
                                ` · ${nodes.length - placedCount} not placed on the map yet`}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={reload}
                            disabled={loading}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </Button>
                        <Button size="sm" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            Add Switch
                        </Button>
                    </div>
                </div>

                {error && (
                    <Card className="border-l-4 border-l-red-500">
                        <CardContent className="flex items-center justify-between gap-4 pt-6">
                            <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <p className="text-sm">{error}</p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={reload}
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="pt-6">
                        <SwitchMap nodes={nodes} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                            <MapPin className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <CardTitle>Switches ({nodes.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {nodes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                ยังไม่มี Switch — เพิ่มรายการแรกของคุณ
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Name
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                IP Address
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Ping
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Last Checked
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {nodes.map((node) => (
                                            <tr
                                                key={node.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {node.name}
                                                    {node.latitude === null && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            (not placed)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {node.ip_address}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        className={statusBadgeClass(
                                                            node.status,
                                                        )}
                                                    >
                                                        {statusLabel(
                                                            node.status,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {node.last_response_time_ms !==
                                                    null
                                                        ? `${node.last_response_time_ms} ms`
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatTime(
                                                        node.last_checked_at,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mr-2"
                                                        onClick={() =>
                                                            openEdit(node)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(node)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editingId ? 'Edit Switch' : 'Add Switch'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Core Switch - Building A"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>
                                    Location{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    size="sm"
                                    value={data.location_mode}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setData(
                                                'location_mode',
                                                value as LocationMode,
                                            );
                                        }
                                    }}
                                    className="w-full"
                                >
                                    <ToggleGroupItem
                                        value="link"
                                        className="flex-1"
                                    >
                                        Google Maps link
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="coordinates"
                                        className="flex-1"
                                    >
                                        Latitude / longitude
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>

                            {data.location_mode === 'link' ? (
                                <div className="space-y-2">
                                    <Label htmlFor="google_maps_url">
                                        Google Maps Link{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="google_maps_url"
                                        value={data.google_maps_url}
                                        onChange={(e) =>
                                            setData(
                                                'google_maps_url',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Paste a Google Maps link"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Paste a Google Maps place/pin/share link
                                        — we'll try to read the coordinates from
                                        it. If it can't be read (e.g. a link
                                        with no coordinates in it), switch to
                                        "Latitude / longitude" instead.
                                    </p>
                                    {errors.google_maps_url && (
                                        <p className="text-sm text-red-500">
                                            {errors.google_maps_url}
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="latitude">
                                            Latitude{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="latitude"
                                            type="number"
                                            step="any"
                                            min={-90}
                                            max={90}
                                            value={data.latitude}
                                            onChange={(e) =>
                                                setData(
                                                    'latitude',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. 13.7563"
                                            required
                                        />
                                        {errors.latitude && (
                                            <p className="text-sm text-red-500">
                                                {errors.latitude}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="longitude">
                                            Longitude{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="longitude"
                                            type="number"
                                            step="any"
                                            min={-180}
                                            max={180}
                                            value={data.longitude}
                                            onChange={(e) =>
                                                setData(
                                                    'longitude',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. 100.5018"
                                            required
                                        />
                                        {errors.longitude && (
                                            <p className="text-sm text-red-500">
                                                {errors.longitude}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="ip_address">
                                    IP Address{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="ip_address"
                                    value={data.ip_address}
                                    onChange={(e) =>
                                        setData('ip_address', e.target.value)
                                    }
                                    placeholder="e.g. 10.0.1.1"
                                    required
                                />
                                {errors.ip_address && (
                                    <p className="text-sm text-red-500">
                                        {errors.ip_address}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="font-normal"
                                >
                                    Actively ping this switch
                                </Label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                {editingId ? 'Update Switch' : 'Add Switch'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
