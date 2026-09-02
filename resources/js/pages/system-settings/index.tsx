import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Gauge,
    LayoutGrid,
    Mail,
    Palette,
    Plus,
    Save,
    Send,
    ShieldCheck,
    Thermometer,
    Trash2,
    Upload,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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

interface NotifyEmail {
    email: string;
    notify: boolean;
}

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
    certificate_exp_warning_days: number;
    notify_alarms_enabled: boolean;
    notify_alarms_interval_minutes: number;
    notify_smart_detection_enabled: boolean;
    notify_smart_detection_interval_minutes: number;
    notify_network_wan_enabled: boolean;
    notify_certificate_enabled: boolean;
    notify_certificate_check_time: string;
    notify_services_enabled: boolean;
    notify_services_interval_minutes: number;
    notify_services_emails: NotifyEmail[];
    notify_services_telegram_enabled: boolean;
    session_timeout_minutes: number;
    disabled_pages: string[];
    room_temp_min_c: number;
    room_temp_max_c: number;
    room_humidity_min_pct: number;
    room_humidity_max_pct: number;
    it_repair_email_header: string | null;
    it_repair_email_subject: string | null;
    it_repair_email_body: string | null;
    it_repair_email_footer: string | null;
    it_repair_email_logo_url: string | null;
    it_repair_email_show_logo: boolean;
    it_repair_email_logo_width: number;
    it_repair_email_heading_color: string;
    it_repair_email_text_color: string;
    it_repair_email_background_color: string;
    it_repair_email_layout: 'full' | 'centered';
    it_repair_email_content_width: number;
}

interface TelegramStatus {
    main_configured: boolean;
    daily_report_configured: boolean;
}

interface Props {
    settings: Settings;
    timezones: string[];
    pages: Record<string, string>;
    telegramStatus: TelegramStatus;
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

function IntervalInput({
    value,
    onChange,
    disabled,
}: {
    value: number;
    onChange: (value: number) => void;
    disabled?: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <Input
                type="number"
                min={1}
                max={60}
                className="w-16"
                value={value}
                disabled={disabled}
                onChange={(e) => onChange(Number(e.target.value))}
            />
            <span className="text-xs text-muted-foreground">min</span>
        </div>
    );
}

function NotificationRow({
    title,
    description,
    enabled,
    onEnabledChange,
    scheduleControl,
    error,
}: {
    title: string;
    description: ReactNode;
    enabled?: boolean;
    onEnabledChange?: (value: boolean) => void;
    scheduleControl: ReactNode;
    error?: string;
}) {
    return (
        <div className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0 sm:flex-1">
                <p className="font-medium">{title}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
                {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
            </div>
            <div className="flex shrink-0 items-center gap-4">
                {scheduleControl}
                {onEnabledChange && (
                    <ToggleSwitch
                        checked={enabled ?? false}
                        onChange={onEnabledChange}
                    />
                )}
            </div>
        </div>
    );
}

export default function Index({
    settings,
    timezones,
    pages,
    telegramStatus,
}: Props) {
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
    const [certificateExpWarningDays, setCertificateExpWarningDays] = useState(
        settings.certificate_exp_warning_days,
    );

    const [alarmsEnabled, setAlarmsEnabled] = useState(
        settings.notify_alarms_enabled,
    );
    const [alarmsIntervalMinutes, setAlarmsIntervalMinutes] = useState(
        settings.notify_alarms_interval_minutes,
    );
    const [smartDetectionEnabled, setSmartDetectionEnabled] = useState(
        settings.notify_smart_detection_enabled,
    );
    const [smartDetectionIntervalMinutes, setSmartDetectionIntervalMinutes] =
        useState(settings.notify_smart_detection_interval_minutes);
    const [networkWanEnabled, setNetworkWanEnabled] = useState(
        settings.notify_network_wan_enabled,
    );
    const [certificateNotifyEnabled, setCertificateNotifyEnabled] = useState(
        settings.notify_certificate_enabled,
    );
    const [certificateCheckTime, setCertificateCheckTime] = useState(
        settings.notify_certificate_check_time,
    );

    const [servicesEnabled, setServicesEnabled] = useState(
        settings.notify_services_enabled,
    );
    const [servicesIntervalMinutes, setServicesIntervalMinutes] = useState(
        settings.notify_services_interval_minutes,
    );
    const [servicesEmails, setServicesEmails] = useState<NotifyEmail[]>(
        settings.notify_services_emails ?? [],
    );
    const [newServiceEmail, setNewServiceEmail] = useState('');

    const addServiceEmail = () => {
        const email = newServiceEmail.trim().toLowerCase();

        if (!email) {
            return;
        }

        if (servicesEmails.some((r) => r.email.toLowerCase() === email)) {
            setNewServiceEmail('');

            return;
        }

        // Added with notification permission off — it has to be granted
        // explicitly before this address receives anything.
        setServicesEmails((prev) => [...prev, { email, notify: false }]);
        setNewServiceEmail('');
    };

    const removeServiceEmail = (email: string) => {
        setServicesEmails((prev) => prev.filter((r) => r.email !== email));
    };

    const setServiceEmailNotify = (email: string, notify: boolean) => {
        setServicesEmails((prev) =>
            prev.map((r) => (r.email === email ? { ...r, notify } : r)),
        );
    };
    const [servicesTelegramEnabled, setServicesTelegramEnabled] = useState(
        settings.notify_services_telegram_enabled,
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

    const [itRepairEmailHeader, setItRepairEmailHeader] = useState(
        settings.it_repair_email_header ?? '',
    );
    const [itRepairEmailSubject, setItRepairEmailSubject] = useState(
        settings.it_repair_email_subject ?? '',
    );
    const [itRepairEmailBody, setItRepairEmailBody] = useState(
        settings.it_repair_email_body ?? '',
    );
    const [itRepairEmailFooter, setItRepairEmailFooter] = useState(
        settings.it_repair_email_footer ?? '',
    );
    const [itRepairEmailLogoFile, setItRepairEmailLogoFile] =
        useState<File | null>(null);
    const [removeItRepairEmailLogo, setRemoveItRepairEmailLogo] =
        useState(false);
    const itRepairEmailLogoInputRef = useRef<HTMLInputElement>(null);
    const [itRepairEmailShowLogo, setItRepairEmailShowLogo] = useState(
        settings.it_repair_email_show_logo,
    );
    const [itRepairEmailLogoWidth, setItRepairEmailLogoWidth] = useState(
        settings.it_repair_email_logo_width,
    );
    const [itRepairEmailHeadingColor, setItRepairEmailHeadingColor] = useState(
        settings.it_repair_email_heading_color,
    );
    const [itRepairEmailTextColor, setItRepairEmailTextColor] = useState(
        settings.it_repair_email_text_color,
    );
    const [itRepairEmailBackgroundColor, setItRepairEmailBackgroundColor] =
        useState(settings.it_repair_email_background_color);
    const [itRepairEmailLayout, setItRepairEmailLayout] = useState<
        'full' | 'centered'
    >(settings.it_repair_email_layout);
    const [itRepairEmailContentWidth, setItRepairEmailContentWidth] = useState(
        settings.it_repair_email_content_width,
    );

    // Array.isArray guard is cheap insurance against a malformed value ever
    // reaching this state — a plain string here would silently decompose
    // into individual characters below (`[...current, key]`), which is
    // exactly how a past data-corruption bug (see SafeJsonArrayCast)
    // manifested as menus flipping on/off seemingly at random.
    const [disabledPages, setDisabledPages] = useState<string[]>(
        Array.isArray(settings.disabled_pages) ? settings.disabled_pages : [],
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

    const itRepairEmailLogoPreview = useMemo(
        () =>
            itRepairEmailLogoFile
                ? URL.createObjectURL(itRepairEmailLogoFile)
                : null,
        [itRepairEmailLogoFile],
    );

    // The uploaded logo if any, else the stored one, else the bundled KU
    // logo shipped at public/image — matches the mailable's fallback.
    const currentItRepairEmailLogoUrl = removeItRepairEmailLogo
        ? '/image/it-repair-email-logo-ku.png'
        : (itRepairEmailLogoPreview ??
          settings.it_repair_email_logo_url ??
          '/image/it-repair-email-logo-ku.png');

    const handleItRepairEmailLogoChange = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const file = e.target.files?.[0] ?? null;
        setItRepairEmailLogoFile(file);
        setRemoveItRepairEmailLogo(false);
    };

    const handleRemoveItRepairEmailLogo = () => {
        setItRepairEmailLogoFile(null);
        setRemoveItRepairEmailLogo(true);

        if (itRepairEmailLogoInputRef.current) {
            itRepairEmailLogoInputRef.current.value = '';
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
                certificate_exp_warning_days: certificateExpWarningDays,
                notify_alarms_enabled: alarmsEnabled,
                notify_alarms_interval_minutes: alarmsIntervalMinutes,
                notify_smart_detection_enabled: smartDetectionEnabled,
                notify_smart_detection_interval_minutes:
                    smartDetectionIntervalMinutes,
                notify_network_wan_enabled: networkWanEnabled,
                notify_certificate_enabled: certificateNotifyEnabled,
                notify_certificate_check_time: certificateCheckTime,
                notify_services_enabled: servicesEnabled,
                notify_services_interval_minutes: servicesIntervalMinutes,
                notify_services_emails: servicesEmails,
                notify_services_telegram_enabled: servicesTelegramEnabled,
                session_timeout_minutes: sessionTimeout,
                disabled_pages: disabledPages,
                room_temp_min_c: roomTempMin,
                room_temp_max_c: roomTempMax,
                room_humidity_min_pct: roomHumidityMin,
                room_humidity_max_pct: roomHumidityMax,
                it_repair_email_header: itRepairEmailHeader,
                it_repair_email_subject: itRepairEmailSubject,
                it_repair_email_body: itRepairEmailBody,
                it_repair_email_footer: itRepairEmailFooter,
                it_repair_email_logo: itRepairEmailLogoFile,
                remove_it_repair_email_logo: removeItRepairEmailLogo,
                it_repair_email_show_logo: itRepairEmailShowLogo,
                it_repair_email_logo_width: itRepairEmailLogoWidth,
                it_repair_email_heading_color: itRepairEmailHeadingColor,
                it_repair_email_text_color: itRepairEmailTextColor,
                it_repair_email_background_color: itRepairEmailBackgroundColor,
                it_repair_email_layout: itRepairEmailLayout,
                it_repair_email_content_width: itRepairEmailContentWidth,
                // Inertia's FormDataConvertible constraint requires an
                // explicit index signature, which the NotifyEmail rows in
                // notify_services_emails don't structurally provide — the
                // payload itself serializes fine as plain form data.
            } as unknown as RequestPayload,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    notifySuccess('บันทึกการตั้งค่าสำเร็จ', 'บันทึกสำเร็จ');
                    setFaviconFile(null);
                    setRemoveFavicon(false);
                    setItRepairEmailLogoFile(null);
                    setRemoveItRepairEmailLogo(false);
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
                            navigation immediately (shown grayed-out to anyone
                            who already has access to it) and block the page
                            itself, without touching individual user permissions
                            — useful for menus that aren&apos;t ready yet.
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
                        <div className="rounded-lg bg-sky-100 p-2 dark:bg-sky-900/30">
                            <Send className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        </div>
                        <CardTitle>Telegram Notifications</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Turn each notification on or off, and adjust how
                            often it's checked. Changes take effect on the next
                            scheduled run — no restart needed.
                        </p>

                        <div className="divide-y rounded-lg border px-4">
                            <NotificationRow
                                title="Alarm Notification"
                                description="A new vCenter alarm triggers, or a VM goes down/powers off."
                                enabled={alarmsEnabled}
                                onEnabledChange={setAlarmsEnabled}
                                scheduleControl={
                                    <IntervalInput
                                        value={alarmsIntervalMinutes}
                                        onChange={setAlarmsIntervalMinutes}
                                        disabled={!alarmsEnabled}
                                    />
                                }
                                error={errors.notify_alarms_interval_minutes}
                            />
                            <NotificationRow
                                title="Smart Detection"
                                description="A new or reopened warning/critical finding (brute force, malware, process, port, or failed service). The underlying scan itself always keeps running on this interval, whether or not the alert is enabled — the Smart Detection page depends on it."
                                enabled={smartDetectionEnabled}
                                onEnabledChange={setSmartDetectionEnabled}
                                scheduleControl={
                                    <IntervalInput
                                        value={smartDetectionIntervalMinutes}
                                        onChange={
                                            setSmartDetectionIntervalMinutes
                                        }
                                    />
                                }
                                error={
                                    errors.notify_smart_detection_interval_minutes
                                }
                            />
                            <NotificationRow
                                title="Network Infrastructure (WAN)"
                                description="A WAN monitor goes down, or recovers. Check frequency is set per-monitor on the Network Infrastructure page, not here."
                                enabled={networkWanEnabled}
                                onEnabledChange={setNetworkWanEnabled}
                                scheduleControl={
                                    <span className="text-xs text-muted-foreground">
                                        Per-monitor
                                    </span>
                                }
                            />
                            <NotificationRow
                                title="Certificate Expiration"
                                description={
                                    <>
                                        Site-wide default lead time before a VM
                                        certificate expires (or is already
                                        expired) — also drives the highlighting
                                        on Manage VMs. Alerted once per
                                        certificate, not repeated daily.
                                        Individual VMs can override this from
                                        their Edit page. See{' '}
                                        <Link
                                            href="/certificate-expiration"
                                            className="underline underline-offset-2"
                                        >
                                            Certificate Expiration
                                        </Link>{' '}
                                        for current status.
                                    </>
                                }
                                enabled={certificateNotifyEnabled}
                                onEnabledChange={setCertificateNotifyEnabled}
                                scheduleControl={
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={365}
                                            className="w-16"
                                            value={certificateExpWarningDays}
                                            onChange={(e) =>
                                                setCertificateExpWarningDays(
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            days before, at
                                        </span>
                                        <Input
                                            type="time"
                                            className="w-28"
                                            value={certificateCheckTime}
                                            disabled={!certificateNotifyEnabled}
                                            onChange={(e) =>
                                                setCertificateCheckTime(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                }
                                error={
                                    errors.certificate_exp_warning_days ??
                                    errors.notify_certificate_check_time
                                }
                            />
                            <NotificationRow
                                title="Service Monitoring"
                                description={
                                    <>
                                        A monitored systemd service (see the{' '}
                                        <Link
                                            href="/services"
                                            className="underline underline-offset-2"
                                        >
                                            Services
                                        </Link>{' '}
                                        page) stops being active. Add/remove
                                        which services to watch from that
                                        page — this only controls the
                                        alert.
                                    </>
                                }
                                enabled={servicesEnabled}
                                onEnabledChange={setServicesEnabled}
                                scheduleControl={
                                    <IntervalInput
                                        value={servicesIntervalMinutes}
                                        onChange={setServicesIntervalMinutes}
                                        disabled={!servicesEnabled}
                                    />
                                }
                                error={
                                    errors.notify_services_interval_minutes
                                }
                            />
                            <NotificationRow
                                title="Daily Report"
                                description="Sent as a PDF when a Daily Report is saved on the Daily Report page — always manual, not on a schedule."
                                scheduleControl={
                                    <span className="text-xs text-muted-foreground">
                                        Manual
                                    </span>
                                }
                            />
                        </div>

                        <div className="space-y-3 rounded-lg border p-4">
                            <div>
                                <p className="text-sm font-medium">
                                    Notify Email
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Recipients for the Service Monitoring
                                    alert above. Add an address first, then
                                    grant it permission to be notified —
                                    only addresses with the toggle on
                                    receive mail. Email is sent in addition
                                    to Telegram, not instead of it.
                                </p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="services-email">
                                    Add email address
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="services-email"
                                        type="email"
                                        placeholder="e.g. ops@example.com"
                                        value={newServiceEmail}
                                        disabled={!servicesEnabled}
                                        onChange={(e) =>
                                            setNewServiceEmail(e.target.value)
                                        }
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addServiceEmail();
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            !servicesEnabled ||
                                            !newServiceEmail.trim()
                                        }
                                        onClick={addServiceEmail}
                                    >
                                        <Plus className="mr-1 h-4 w-4" />
                                        Add
                                    </Button>
                                </div>
                                {errors.notify_services_emails && (
                                    <p className="text-xs text-red-500">
                                        {errors.notify_services_emails}
                                    </p>
                                )}
                                {Object.entries(errors)
                                    .filter(([key]) =>
                                        key.startsWith(
                                            'notify_services_emails.',
                                        ),
                                    )
                                    .map(([key, message]) => (
                                        <p
                                            key={key}
                                            className="text-xs text-red-500"
                                        >
                                            {message}
                                        </p>
                                    ))}
                            </div>

                            {servicesEmails.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No addresses added — email notifications
                                    are off. Telegram (below) still applies.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {servicesEmails.map((row) => (
                                        <li
                                            key={row.email}
                                            className="flex items-center justify-between gap-3 px-3 py-2"
                                        >
                                            <span className="min-w-0 flex-1 truncate text-sm">
                                                {row.email}
                                            </span>
                                            <div className="flex shrink-0 items-center gap-3">
                                                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    Send notifications
                                                    <ToggleSwitch
                                                        checked={row.notify}
                                                        onChange={(value) =>
                                                            setServiceEmailNotify(
                                                                row.email,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </label>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        removeServiceEmail(
                                                            row.email,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="flex items-center justify-between">
                                <span className="text-sm">
                                    Also send via Telegram
                                </span>
                                <ToggleSwitch
                                    checked={servicesTelegramEnabled}
                                    onChange={setServicesTelegramEnabled}
                                />
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Badge
                                className={
                                    telegramStatus.main_configured
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800/60 dark:text-gray-400'
                                }
                            >
                                Main bot:{' '}
                                {telegramStatus.main_configured
                                    ? 'Configured'
                                    : 'Not configured'}
                            </Badge>
                            <Badge
                                className={
                                    telegramStatus.daily_report_configured
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800/60 dark:text-gray-400'
                                }
                            >
                                Daily Report bot:{' '}
                                {telegramStatus.daily_report_configured
                                    ? 'Configured'
                                    : 'Not configured'}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Bot tokens and chat IDs are set via environment
                            variables (
                            <code className="rounded bg-muted px-1 py-0.5">
                                TELEGRAM_BOT_TOKEN
                            </code>
                            /
                            <code className="rounded bg-muted px-1 py-0.5">
                                TELEGRAM_CHAT_ID
                            </code>
                            , and a separate pair for the Daily Report), not
                            editable from this page.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-indigo-100 p-2 dark:bg-indigo-900/30">
                            <Mail className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <CardTitle>IT Repair Notification Email</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Content sent by the &quot;Send Email&quot; button
                            on the{' '}
                            <Link
                                href="/it-repair"
                                className="underline underline-offset-2"
                            >
                                IT Repair
                            </Link>{' '}
                            page — it emails the recipient who filed the
                            request. Use these placeholders anywhere below
                            and they&apos;ll be filled in per request:{' '}
                            {[
                                'full_name',
                                'recipient_email',
                                'contact_number',
                                'service_type',
                                'provider_name',
                                'status_label',
                                'details',
                                'requested_at',
                                'request_id',
                                'tracking_link',
                            ].map((token, i, arr) => (
                                <span key={token}>
                                    <code className="rounded bg-muted px-1 py-0.5">
                                        {`{{${token}}}`}
                                    </code>
                                    {i < arr.length - 1 ? ', ' : ''}
                                </span>
                            ))}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            The email body is plain text, not HTML — for{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                {'{{tracking_link}}'}
                            </code>{' '}
                            just drop it in on its own, e.g. &quot;Track your
                            request: {'{{tracking_link}}'}&quot;. Don&apos;t
                            wrap it in an{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                {'<a href>'}
                            </code>{' '}
                            tag — email clients turn a plain https:// link
                            into a clickable one automatically, but literal
                            HTML tags show up as text instead of a link.
                        </p>

                        <div className="space-y-2">
                            <Label htmlFor="repair-email-header">
                                Header
                            </Label>
                            <Input
                                id="repair-email-header"
                                value={itRepairEmailHeader}
                                onChange={(e) =>
                                    setItRepairEmailHeader(e.target.value)
                                }
                                placeholder="IT Repair Request Update"
                            />
                            {errors.it_repair_email_header && (
                                <p className="text-xs text-red-500">
                                    {errors.it_repair_email_header}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="repair-email-subject">
                                Subject
                            </Label>
                            <Input
                                id="repair-email-subject"
                                value={itRepairEmailSubject}
                                onChange={(e) =>
                                    setItRepairEmailSubject(e.target.value)
                                }
                                placeholder="IT Repair Request Update — {{full_name}}"
                            />
                            {errors.it_repair_email_subject && (
                                <p className="text-xs text-red-500">
                                    {errors.it_repair_email_subject}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="repair-email-body">
                                Details
                            </Label>
                            <textarea
                                id="repair-email-body"
                                className="flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={itRepairEmailBody}
                                onChange={(e) =>
                                    setItRepairEmailBody(e.target.value)
                                }
                                placeholder={
                                    'Hello {{full_name}},\n\nYour IT repair request is now: {{status_label}}.'
                                }
                            />
                            {errors.it_repair_email_body && (
                                <p className="text-xs text-red-500">
                                    {errors.it_repair_email_body}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="repair-email-footer">
                                Footer
                            </Label>
                            <textarea
                                id="repair-email-footer"
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={itRepairEmailFooter}
                                onChange={(e) =>
                                    setItRepairEmailFooter(e.target.value)
                                }
                                placeholder="This is an automated message — please do not reply."
                            />
                            {errors.it_repair_email_footer && (
                                <p className="text-xs text-red-500">
                                    {errors.it_repair_email_footer}
                                </p>
                            )}
                        </div>

                        <div className="border-t pt-4">
                            <p className="text-sm font-medium">
                                Template design
                            </p>
                            <p className="text-xs text-muted-foreground">
                                The logo, colours and layout of the email
                                itself — everything wrapped around the text
                                above.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label>Logo</Label>
                            <div className="flex items-center gap-4">
                                <div
                                    className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border"
                                    style={{
                                        backgroundColor:
                                            itRepairEmailBackgroundColor,
                                    }}
                                >
                                    <img
                                        src={currentItRepairEmailLogoUrl}
                                        alt="Email logo preview"
                                        className="max-h-full max-w-full object-contain"
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <input
                                        ref={itRepairEmailLogoInputRef}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleItRepairEmailLogoChange}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            itRepairEmailLogoInputRef.current?.click()
                                        }
                                    >
                                        <Upload className="mr-2 h-4 w-4" />
                                        Upload logo
                                    </Button>
                                    {(settings.it_repair_email_logo_url ||
                                        itRepairEmailLogoFile) &&
                                        !removeItRepairEmailLogo && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={
                                                    handleRemoveItRepairEmailLogo
                                                }
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Use default KU logo
                                            </Button>
                                        )}
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                PNG or JPG, up to 1 MB. Leave unset to use the
                                bundled Kasetsart University logo.
                            </p>
                            {errors.it_repair_email_logo && (
                                <p className="text-xs text-red-500">
                                    {errors.it_repair_email_logo}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p className="text-sm font-medium">
                                    Show the logo in the email
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Turn off for a plain, logo-free message.
                                </p>
                            </div>
                            <ToggleSwitch
                                id="repair-email-show-logo"
                                checked={itRepairEmailShowLogo}
                                onChange={setItRepairEmailShowLogo}
                            />
                        </div>

                        {itRepairEmailShowLogo && (
                            <div className="space-y-2">
                                <Label htmlFor="repair-email-logo-width">
                                    Logo width (px)
                                </Label>
                                <Input
                                    id="repair-email-logo-width"
                                    type="number"
                                    min={16}
                                    max={400}
                                    className="max-w-[8rem]"
                                    value={itRepairEmailLogoWidth}
                                    onChange={(e) =>
                                        setItRepairEmailLogoWidth(
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                {errors.it_repair_email_logo_width && (
                                    <p className="text-xs text-red-500">
                                        {errors.it_repair_email_logo_width}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    {
                                        id: 'repair-email-heading-color',
                                        label: 'Heading colour',
                                        value: itRepairEmailHeadingColor,
                                        set: setItRepairEmailHeadingColor,
                                        error: errors.it_repair_email_heading_color,
                                    },
                                    {
                                        id: 'repair-email-text-color',
                                        label: 'Body text colour',
                                        value: itRepairEmailTextColor,
                                        set: setItRepairEmailTextColor,
                                        error: errors.it_repair_email_text_color,
                                    },
                                    {
                                        id: 'repair-email-bg-color',
                                        label: 'Background colour',
                                        value: itRepairEmailBackgroundColor,
                                        set: setItRepairEmailBackgroundColor,
                                        error: errors.it_repair_email_background_color,
                                    },
                                ] as const
                            ).map((c) => (
                                <div key={c.id} className="space-y-2">
                                    <Label htmlFor={c.id}>{c.label}</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            id={c.id}
                                            type="color"
                                            className="h-9 w-10 shrink-0 cursor-pointer rounded border border-input bg-transparent p-1"
                                            value={c.value}
                                            onChange={(e) =>
                                                c.set(e.target.value)
                                            }
                                        />
                                        <Input
                                            aria-label={`${c.label} hex`}
                                            className="font-mono"
                                            value={c.value}
                                            onChange={(e) =>
                                                c.set(e.target.value)
                                            }
                                            placeholder="#000000"
                                        />
                                    </div>
                                    {c.error && (
                                        <p className="text-xs text-red-500">
                                            {c.error}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="repair-email-layout">
                                    Layout
                                </Label>
                                <Select
                                    value={itRepairEmailLayout}
                                    onValueChange={(v) =>
                                        setItRepairEmailLayout(
                                            v as 'full' | 'centered',
                                        )
                                    }
                                >
                                    <SelectTrigger id="repair-email-layout">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="full">
                                            Full width (fills the reading pane)
                                        </SelectItem>
                                        <SelectItem value="centered">
                                            Centred column
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.it_repair_email_layout && (
                                    <p className="text-xs text-red-500">
                                        {errors.it_repair_email_layout}
                                    </p>
                                )}
                            </div>

                            {itRepairEmailLayout === 'centered' && (
                                <div className="space-y-2">
                                    <Label htmlFor="repair-email-content-width">
                                        Column width (px)
                                    </Label>
                                    <Input
                                        id="repair-email-content-width"
                                        type="number"
                                        min={320}
                                        max={1200}
                                        value={itRepairEmailContentWidth}
                                        onChange={(e) =>
                                            setItRepairEmailContentWidth(
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    {errors.it_repair_email_content_width && (
                                        <p className="text-xs text-red-500">
                                            {
                                                errors.it_repair_email_content_width
                                            }
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Preview</Label>
                            <div
                                className="overflow-hidden rounded-lg border"
                                style={{
                                    backgroundColor:
                                        itRepairEmailBackgroundColor,
                                }}
                            >
                                <div
                                    style={{
                                        padding: '32px 28px',
                                        margin:
                                            itRepairEmailLayout === 'centered'
                                                ? '0 auto'
                                                : undefined,
                                        maxWidth:
                                            itRepairEmailLayout === 'centered'
                                                ? `${itRepairEmailContentWidth}px`
                                                : undefined,
                                    }}
                                >
                                    {itRepairEmailShowLogo && (
                                        <img
                                            src={currentItRepairEmailLogoUrl}
                                            alt="Kasetsart University"
                                            style={{
                                                display: 'block',
                                                width: `${itRepairEmailLogoWidth}px`,
                                                height: 'auto',
                                                marginBottom: '24px',
                                            }}
                                        />
                                    )}
                                    <div
                                        style={{
                                            margin: '0 0 16px',
                                            fontSize: '18px',
                                            fontWeight: 700,
                                            lineHeight: 1.4,
                                            color: itRepairEmailHeadingColor,
                                        }}
                                    >
                                        {itRepairEmailHeader ||
                                            'IT Repair Request Update'}
                                    </div>
                                    <div
                                        style={{
                                            whiteSpace: 'pre-wrap',
                                            fontSize: '14px',
                                            lineHeight: 1.6,
                                            color: itRepairEmailTextColor,
                                        }}
                                    >
                                        {itRepairEmailBody ||
                                            'Hello {{full_name}}, your IT repair request is now: {{status_label}}.'}
                                    </div>
                                    {itRepairEmailFooter.trim() !== '' && (
                                        <div
                                            style={{
                                                marginTop: '28px',
                                                paddingTop: '16px',
                                                borderTop: '1px solid #e4e4e7',
                                                fontSize: '12px',
                                                lineHeight: 1.5,
                                                color: '#71717a',
                                                whiteSpace: 'pre-wrap',
                                            }}
                                        >
                                            {itRepairEmailFooter}
                                        </div>
                                    )}
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Placeholders like{' '}
                                <code className="rounded bg-muted px-1 py-0.5">
                                    {'{{full_name}}'}
                                </code>{' '}
                                are shown literally here — they&apos;re filled
                                in per request when the email is sent.
                            </p>
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
                            temperature/humidity sensor, shown as the two gauge
                            cards on the Dashboard. A reading outside this range
                            is flagged as abnormal; no sensor is connected yet,
                            so both gauges show &quot;No Data&quot; until one
                            starts reporting.
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
