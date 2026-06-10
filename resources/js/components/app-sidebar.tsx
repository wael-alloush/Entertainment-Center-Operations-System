import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { History, LayoutDashboard, Moon, PlayCircle, Settings2, Sun, Users } from 'lucide-react';
import AppLogo from './app-logo';
import { Auth } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'الرئيسية', url: '/dashboard', icon: LayoutDashboard },
    { title: 'الفواتير النشطة', url: '/invoices/active', icon: PlayCircle },
    { title: 'الفواتير السابقة', url: '/invoices/completed', icon: History },
    { title: 'الموظفين', url: '/employees', icon: Users },
];

const adminNavItems: NavItem[] = [{ title: 'إدارة النظام', url: '/management', icon: Settings2 }];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { appearance, updateAppearance } = useAppearance();
    const isDark = appearance === 'dark';
    const navItems = auth.user?.is_admin ? [...mainNavItems, ...adminNavItems] : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset" side="right" className="border-sidebar-border/60">
            <SidebarHeader className="border-sidebar-border/60 border-b p-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="rounded-xl">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-2 py-4">
                <NavMain items={navItems} label="القائمة الرئيسية" />
            </SidebarContent>

            <SidebarFooter className="border-sidebar-border/60 space-y-3 border-t p-4">
                <button
                    type="button"
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    className="text-muted-foreground hover:bg-sidebar-accent hover:text-foreground flex w-full items-center justify-center gap-2 rounded-xl border border-sidebar-border/60 px-3 py-2 text-sm transition-colors"
                >
                    {isDark ? <Sun className="size-4 text-amber-500" /> : <Moon className="size-4 text-primary" />}
                    <span className="group-data-[collapsible=icon]:hidden">{isDark ? 'الوضع الفاتح' : 'الوضع الداكن'}</span>
                </button>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
