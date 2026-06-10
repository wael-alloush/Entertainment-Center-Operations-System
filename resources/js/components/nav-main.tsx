import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], label = 'Platform' }: { items: NavItem[]; label?: string }) {
    const page = usePage();

    return (
        <SidebarGroup className="px-1 py-0">
            <SidebarGroupLabel className="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="space-y-1">
                {items.map((item) => {
                    const isActive = page.url === item.url || page.url.startsWith(`${item.url}?`) || page.url.startsWith(`${item.url}/`);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                className={cn(
                                    'h-11 rounded-xl transition-all duration-200',
                                    isActive && 'raad-nav-active hover:bg-primary hover:text-primary-foreground',
                                )}
                            >
                                <Link href={item.url} prefetch className="gap-3">
                                    {item.icon && (
                                        <span
                                            data-nav-icon
                                            className={cn(
                                                'raad-icon-chip bg-sidebar-accent/80 text-muted-foreground',
                                                isActive && 'bg-primary-foreground/15 text-primary-foreground',
                                            )}
                                        >
                                            <item.icon className="size-[1.125rem]" strokeWidth={2.25} />
                                        </span>
                                    )}
                                    <span className="font-medium">{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
