import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    LayoutGrid,
    Server,
    Monitor,
    ClipboardList,
    Users,
} from 'lucide-react';
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
        title: 'Manage Users',
        href: '/users',
        icon: Users,
        adminOnly: true,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const user = auth.user;

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
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
