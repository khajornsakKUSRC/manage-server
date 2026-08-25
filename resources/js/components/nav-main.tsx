import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.disabled ? (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                disabled
                                aria-disabled="true"
                                className="cursor-not-allowed opacity-50"
                                tooltip={{
                                    children: `${item.title} (ปิดใช้งานชั่วคราว)`,
                                }}
                            >
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                            {!!item.badge && (
                                <SidebarMenuBadge
                                    className={`bg-red-500 text-white ${
                                        item.badgePulse
                                            ? 'animate-badge-flash'
                                            : ''
                                    }`}
                                >
                                    {item.badge > 99 ? '99+' : item.badge}
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
