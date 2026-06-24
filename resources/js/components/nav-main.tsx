import { Badge } from '@/components/ui/badge';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], label = 'Platform' }: { items: NavItem[]; label?: string }) {
    const page = usePage();

    return (
        <SidebarGroup className="px-1 py-0 group-data-[collapsible=icon]:px-0">
            <SidebarGroupLabel className="text-muted-foreground mb-2 px-2 text-xs font-semibold tracking-wide group-data-[collapsible=icon]:hidden">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="space-y-1 group-data-[collapsible=icon]:items-center">
                {items.map((item) => {
                    const isActive = page.url === item.url || page.url.startsWith(`${item.url}?`) || page.url.startsWith(`${item.url}/`);

                    return (
                        <SidebarMenuItem key={item.title} className="group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center">
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={item.title}
                                className="h-11 rounded-xl transition-all duration-200 group-data-[collapsible=icon]:size-10"
                            >
                                <Link
                                    href={item.url}
                                    prefetch
                                    className="flex items-center gap-3 group-data-[collapsible=icon]:size-full group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0"
                                >
                                    {item.icon && (
                                        <span
                                            data-nav-icon
                                            className={cn(
                                                'raad-icon-chip size-9 bg-sidebar-accent/80 text-muted-foreground',
                                                'group-data-[collapsible=icon]:size-auto group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:shadow-none',
                                                isActive && 'bg-primary-foreground text-primary shadow-sm',
                                                isActive &&
                                                    'group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:text-inherit',
                                            )}
                                        >
                                            <item.icon
                                                className="size-[1.125rem] group-data-[collapsible=icon]:size-5"
                                                strokeWidth={2.5}
                                            />
                                        </span>
                                    )}
                                    <span className="font-medium group-data-[collapsible=icon]:hidden">{item.title}</span>
                                    {item.badge !== undefined && item.badge > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ms-auto bg-amber-500/15 text-xs text-amber-700 group-data-[collapsible=icon]:hidden dark:text-amber-300"
                                        >
                                            {item.badge}
                                        </Badge>
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
