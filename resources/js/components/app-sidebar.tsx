import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BellRing,
    Database,
    History,
    LayoutGrid,
    LineChart,
    Server,
    Monitor,
    ClipboardList,
    Settings,
    ShieldAlert,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type PermissionedNavItem = NavItem & {
    permission?: string;
    adminOnly?: boolean;
};

const mainNavItems: PermissionedNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        permission: 'dashboard',
    },
    {
        title: 'Manage Hosts',
        href: '/hosts',
        icon: Server,
        permission: 'hosts',
    },
    {
        title: 'Manage VMs',
        href: '/vms',
        icon: Monitor,
        permission: 'vms',
    },
    {
        title: 'Appliance Health',
        href: '/appliance',
        icon: Activity,
        permission: 'appliance',
    },
    {
        title: 'Daily Report',
        href: '/daily-reports',
        icon: ClipboardList,
        permission: 'daily-reports',
    },
    {
        title: 'Alarm Notification',
        href: '/alarms',
        icon: BellRing,
        permission: 'alarms',
    },
    {
        title: 'Datastore',
        href: '/datastores',
        icon: Database,
        permission: 'datastores',
    },
    {
        title: 'Performance',
        href: '/performance',
        icon: LineChart,
        permission: 'performance',
    },
    {
        title: 'Mod Security',
        href: '/modsecurity',
        icon: ShieldAlert,
        permission: 'modsecurity',
    },
    {
        title: 'Manage Users',
        href: '/users',
        icon: Users,
        adminOnly: true,
    },
    {
        title: 'Activity Log',
        href: '/activity-log',
        icon: History,
        adminOnly: true,
    },
    {
        title: 'Settings',
        href: '/system-settings',
        icon: Settings,
        adminOnly: true,
    },
];

const ALARM_COUNT_POLL_MS = 60_000;

export function AppSidebar() {
    const { auth, siteSettings } = usePage().props;
    const user = auth.user;
    const disabledPages = new Set(siteSettings.disabled_pages ?? []);

    const visibleNavItems = mainNavItems.filter((item) => {
        if (!user) {
            return false;
        }

        if (user.is_admin) {
            return true;
        }

        if (item.adminOnly) {
            return false;
        }

        return (
            !item.permission ||
            (user.permissions ?? []).includes(item.permission)
        );
    });

    const hasAlarmsAccess = visibleNavItems.some(
        (item) => item.href === '/alarms',
    );

    const [alarmCount, setAlarmCount] = useState(0);

    // Polls a lightweight count-only endpoint (no AI hints, no alarm-name
    // resolution) so the sidebar badge can refresh often without the cost
    // of the full Alarm Notification page's data.
    useEffect(() => {
        if (!hasAlarmsAccess) {
            return;
        }

        let cancelled = false;

        const load = () => {
            fetch('/api/vsphere/alarms/count')
                .then((res) => (res.ok ? res.json() : Promise.reject()))
                .then((json) => {
                    if (!cancelled) {
                        setAlarmCount(
                            typeof json.data === 'number' ? json.data : 0,
                        );
                    }
                })
                .catch(() => {
                    // Silently ignore — the badge just keeps its last known count.
                });
        };

        load();
        const interval = setInterval(load, ALARM_COUNT_POLL_MS);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [hasAlarmsAccess]);

    const itemsWithBadges = visibleNavItems.map((item) => ({
        ...item,
        // Keeps blinking the whole time there's at least one triggered
        // alarm — like an unread-message badge — rather than just a brief
        // flash, so it stays noticeable until the alarms are cleared.
        ...(item.href === '/alarms'
            ? { badge: alarmCount, badgePulse: alarmCount > 0 }
            : null),
        disabled: !!item.permission && disabledPages.has(item.permission),
    }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={itemsWithBadges} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
