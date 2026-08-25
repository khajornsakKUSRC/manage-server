import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Gauge,
    LayoutGrid,
    Palette,
    Save,
    ShieldCheck,
    Thermometer,
    Trash2,
    Upload,
    Wrench,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyError, notifySuccess } from '@/lib/swal';

interface Settings {
    maintenance_mode_enabled: boolean;
    maintenance_message: string | null;
    favicon_url: string | null;
    timezone: string;
    footer_text: string | null;
    cpu_warning_pct: number;
    cpu_critical_pct: number;
    mem_warning_pct: number;
    mem_critical_pct: number;
    datastore_warning_pct: number;
    datastore_critical_pct: number;
    session_timeout_minutes: number;
    disabled_pages: string[];
    room_temp_min_c: number;
    room_temp_max_c: number;
    room_humidity_min_pct: number;
    room_humidity_max_pct: number;
}

interface Props {
    settings: Settings;
    timezones: string[];
    pages: Record<string, string>;
}

function ToggleSwitch({
    checked,
    onChange,
    id,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    id?: string;
}) {
    return (
        <button
            type="button"
            id={id}
            role="switch"
            aria-checked={checked}
            onClick={() => onChange(!checked)}
            className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors ${
                checked ? 'bg-primary' : 'bg-muted'
            }`}
        >
            <span
                className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                    checked ? 'translate-x-6' : 'translate-x-1'
                }`}
            />
        </button>
    );
}

function ThresholdRow({
    label,
    warning,
    critical,
    onWarningChange,
    onCriticalChange,
    error,
}: {
    label: string;
    warning: number;
    critical: number;
    onWarningChange: (value: number) => void;
    onCriticalChange: (value: number) => void;
    error?: string;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="flex items-center gap-3">
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        Warning
                    </span>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        className="w-20"
                        value={warning}
                        onChange={(e) =>
                            onWarningChange(Number(e.target.value))
                        }
                    />
                    <span className="text-xs text-muted-foreground">%</span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        Critical
                    </span>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        className="w-20"
                        value={critical}
                        onChange={(e) =>
                            onCriticalChange(Number(e.target.value))
                        }
                    />
                    <span className="text-xs text-muted-foreground">%</span>
                </div>
            </div>
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

function RangeRow({
    label,
    unit,
    min,
    max,
    onMinChange,
    onMaxChange,
    error,
}: {
    label: string;
    unit: string;
    min: number;
    max: number;
    onMinChange: (value: number) => void;
    onMaxChange: (value: number) => void;
    error?: string;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="flex items-center gap-3">
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">Min</span>
                    <Input
                        type="number"
                        className="w-20"
                        value={min}
                        onChange={(e) => onMinChange(Number(e.target.value))}
                    />
                    <span className="text-xs text-muted-foreground">
                        {unit}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">Max</span>
                    <Input
                        type="number"
                        className="w-20"
                        value={max}
                        onChange={(e) => onMaxChange(Number(e.target.value))}
                    />
                    <span className="text-xs text-muted-foreground">
                        {unit}
                    </span>
                </div>
            </div>
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function Index({ settings, timezones, pages }: Props) {
    const [maintenanceEnabled, setMaintenanceEnabled] = useState(
        settings.maintenance_mode_enabled,
    );
    const [maintenanceMessage, setMaintenanceMessage] = useState(
        settings.maintenance_message ?? '',
    );

    const [faviconFile, setFaviconFile] = useState<File | null>(null);
    const [removeFavicon, setRemoveFavicon] = useState(false);
    const faviconInputRef = useRef<HTMLInputElement>(null);

    const [timezone, setTimezone] = useState(settings.timezone);
    const [footerText, setFooterText] = useState(settings.footer_text ?? '');

    const [cpuWarning, setCpuWarning] = useState(settings.cpu_warning_pct);
    const [cpuCritical, setCpuCritical] = useState(settings.cpu_critical_pct);
    const [memWarning, setMemWarning] = useState(settings.mem_warning_pct);
    const [memCritical, setMemCritical] = useState(settings.mem_critical_pct);
    const [datastoreWarning, setDatastoreWarning] = useState(
        settings.datastore_warning_pct,
    );
    const [datastoreCritical, setDatastoreCritical] = useState(
        settings.datastore_critical_pct,
    );

    const [roomTempMin, setRoomTempMin] = useState(settings.room_temp_min_c);
    const [roomTempMax, setRoomTempMax] = useState(settings.room_temp_max_c);
    const [roomHumidityMin, setRoomHumidityMin] = useState(
        settings.room_humidity_min_pct,
    );
    const [roomHumidityMax, setRoomHumidityMax] = useState(
        settings.room_humidity_max_pct,
    );

    const [sessionTimeout, setSessionTimeout] = useState(
        settings.session_timeout_minutes,
    );

    const [disabledPages, setDisabledPages] = useState<string[]>(
        settings.disabled_pages,
    );

    const togglePage = (key: string, enabled: boolean) => {
        setDisabledPages((current) =>
            enabled
                ? current.filter((page) => page !== key)
                : [...current, key],
        );
    };

    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const faviconPreview = useMemo(
        () => (faviconFile ? URL.createObjectURL(faviconFile) : null),
        [faviconFile],
    );

    const currentFaviconUrl = removeFavicon
        ? null
        : (faviconPreview ?? settings.favicon_url);

    const handleFaviconChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setFaviconFile(file);
        setRemoveFavicon(false);
    };

    const handleRemoveFavicon = () => {
        setFaviconFile(null);
        setRemoveFavicon(true);

        if (faviconInputRef.current) {
            faviconInputRef.current.value = '';
        }
    };

    const handleSave = () => {
        setSaving(true);
        setErrors({});

        router.post(
            '/system-settings',
            {
                maintenance_mode_enabled: maintenanceEnabled,
                maintenance_message: maintenanceMessage,
                favicon: faviconFile,
                remove_favicon: removeFavicon,
                timezone,
                footer_text: footerText,
                cpu_warning_pct: cpuWarning,
                cpu_critical_pct: cpuCritical,
                mem_warning_pct: memWarning,
                mem_critical_pct: memCritical,
                datastore_warning_pct: datastoreWarning,
                datastore_critical_pct: datastoreCritical,
                session_timeout_minutes: sessionTimeout,
                disabled_pages: disabledPages,
                room_temp_min_c: roomTempMin,
                room_temp_max_c: roomTempMax,
                room_humidity_min_pct: roomHumidityMin,
                room_humidity_max_pct: roomHumidityMax,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    notifySuccess('บันทึกการตั้งค่าสำเร็จ', 'บันทึกสำเร็จ');
                    setFaviconFile(null);
                    setRemoveFavicon(false);
                },
                onError: (formErrors) => {
                    setErrors(formErrors as Record<string, string>);
                    const message =
                        Object.values(formErrors)[0] ??
                        'กรุณาตรวจสอบข้อมูลอีกครั้ง';
                    notifyError(message, 'บันทึกไม่สำเร็จ');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">Settings</h1>
                        <p className="text-sm text-muted-foreground">
                            System-wide configuration for maintenance notices,
                            branding, monitoring thresholds, and security.
                        </p>
                    </div>
                    <Button onClick={handleSave} disabled={saving}>
                        <Save className="mr-2 h-4 w-4" />
                        {saving ? 'กำลังบันทึก...' : 'Save Settings'}
                    </Button>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                            <Wrench className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <CardTitle>Maintenance Notice</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p className="text-sm font-medium">
                                    Show a notice before login
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Displays a pop-up alert on the login page
                                    warning users about scheduled repairs or
                                    downtime.
                                </p>
                            </div>
                            <ToggleSwitch
                                checked={maintenanceEnabled}
                                onChange={setMaintenanceEnabled}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="maintenance-message">Message</Label>
                            <textarea
                                id="maintenance-message"
                                className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={maintenanceMessage}
                                onChange={(e) =>
                                    setMaintenanceMessage(e.target.value)
                                }
                                placeholder="เช่น ระบบจะปิดปรับปรุงวันที่ 25 ส.ค. เวลา 22:00-24:00 น."
                            />
                            {errors.maintenance_message && (
                                <p className="text-xs text-red-500">
                                    {errors.maintenance_message}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                            <LayoutGrid className="h-4 w-4 text-teal-600 dark:text-teal-400" />
                        </div>
                        <CardTitle>Menus</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <p className="mb-2 text-xs text-muted-foreground">
                            Turn a menu off to hide it from everyone&apos;s
                            navigation immediately (shown grayed-out to
                            anyone who already has access to it) and block
                            the page itself, without touching individual
                            user permissions — useful for menus that aren&apos;t
                            ready yet.
                        </p>
                        {Object.entries(pages).map(([key, label]) => {
                            const enabled = !disabledPages.includes(key);

                            return (
                                <div
                                    key={key}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <p className="text-sm font-medium">
                                        {label}
                                    </p>
                                    <ToggleSwitch
                                        checked={enabled}
                                        onChange={(value) =>
                                            togglePage(key, value)
                                        }
                                    />
                                </div>
                            );
                        })}
                        {errors.disabled_pages && (
                            <p className="text-xs text-red-500">
                                {errors.disabled_pages}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                            <Palette className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <CardTitle>Branding</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Website Icon (Favicon)</Label>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border bg-muted/30">
                                    {currentFaviconUrl ? (
                                        <img
                                            src={currentFaviconUrl}
                                            alt="Favicon preview"
                                            className="h-8 w-8 object-contain"
                                        />
                                    ) : (
                                        <span className="text-[10px] text-muted-foreground">
                                            None
                                        </span>
                                    )}
                                </div>
                                <input
                                    ref={faviconInputRef}
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={handleFaviconChange}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        faviconInputRef.current?.click()
                                    }
                                >
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload
                                </Button>
                                {currentFaviconUrl && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleRemoveFavicon}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                )}
                            </div>
                            {errors.favicon && (
                                <p className="text-xs text-red-500">
                                    {errors.favicon}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Timezone</Label>
                            <Select
                                value={timezone}
                                onValueChange={setTimezone}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="เลือก Timezone" />
                                </SelectTrigger>
                                <SelectContent>
                                    {timezones.map((tz) => (
                                        <SelectItem key={tz} value={tz}>
                                            {tz}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.timezone && (
                                <p className="text-xs text-red-500">
                                    {errors.timezone}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="footer-text">Website Footer</Label>
                            <Input
                                id="footer-text"
                                value={footerText}
                                onChange={(e) => setFooterText(e.target.value)}
                                placeholder="© 2026 Manage Server"
                            />
                            {errors.footer_text && (
                                <p className="text-xs text-red-500">
                                    {errors.footer_text}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <Gauge className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <CardTitle>Monitoring Thresholds</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400">
                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>
                                These thresholds control the color coding on the
                                Appliance Health (CPU/Memory) and Datastore
                                pages. Critical must be greater than or equal to
                                Warning.
                            </span>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-3">
                            <ThresholdRow
                                label="CPU"
                                warning={cpuWarning}
                                critical={cpuCritical}
                                onWarningChange={setCpuWarning}
                                onCriticalChange={setCpuCritical}
                                error={errors.cpu_critical_pct}
                            />
                            <ThresholdRow
                                label="Memory"
                                warning={memWarning}
                                critical={memCritical}
                                onWarningChange={setMemWarning}
                                onCriticalChange={setMemCritical}
                                error={errors.mem_critical_pct}
                            />
                            <ThresholdRow
                                label="Datastore"
                                warning={datastoreWarning}
                                critical={datastoreCritical}
                                onWarningChange={setDatastoreWarning}
                                onCriticalChange={setDatastoreCritical}
                                error={errors.datastore_critical_pct}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-cyan-100 p-2 dark:bg-cyan-900/30">
                            <Thermometer className="h-4 w-4 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <CardTitle>Server Room Environment</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <p className="text-xs text-muted-foreground">
                            Normal range for the server room&apos;s
                            temperature/humidity sensor, shown as the two
                            gauge cards on the Dashboard. A reading outside
                            this range is flagged as abnormal; no sensor is
                            connected yet, so both gauges show &quot;No
                            Data&quot; until one starts reporting.
                        </p>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <RangeRow
                                label="Temperature"
                                unit="°C"
                                min={roomTempMin}
                                max={roomTempMax}
                                onMinChange={setRoomTempMin}
                                onMaxChange={setRoomTempMax}
                                error={errors.room_temp_max_c}
                            />
                            <RangeRow
                                label="Humidity"
                                unit="%"
                                min={roomHumidityMin}
                                max={roomHumidityMax}
                                onMinChange={setRoomHumidityMin}
                                onMaxChange={setRoomHumidityMax}
                                error={errors.room_humidity_max_pct}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                            <ShieldCheck className="h-4 w-4 text-red-600 dark:text-red-400" />
                        </div>
                        <CardTitle>Security</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="max-w-xs space-y-2">
                            <Label htmlFor="session-timeout">
                                Session Timeout (minutes)
                            </Label>
                            <Input
                                id="session-timeout"
                                type="number"
                                min={1}
                                max={43200}
                                value={sessionTimeout}
                                onChange={(e) =>
                                    setSessionTimeout(Number(e.target.value))
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Users are logged out after this many minutes of
                                inactivity.
                            </p>
                            {errors.session_timeout_minutes && (
                                <p className="text-xs text-red-500">
                                    {errors.session_timeout_minutes}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
