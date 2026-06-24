import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Gift, History, Moon, PlayCircle, Settings2, Sun, Users, Wallet } from 'lucide-react';
import AppLogo from './app-logo';
import { SharedData } from '@/types';

const adminNavItems: NavItem[] = [{ title: 'إدارة النظام', url: '/management', icon: Settings2 }];

export function AppSidebar() {
    const { auth, name, pendingBonusCount, pendingCreditCount } = usePage<SharedData>().props;
    const { appearance, updateAppearance } = useAppearance();
    const isDark = appearance === 'dark';

    const mainNavItems: NavItem[] = [
        { title: 'الفواتير النشطة', url: '/invoices/active', icon: PlayCircle },
        { title: 'الفواتير السابقة', url: '/invoices/completed', icon: History },
        {
            title: 'المكافآت',
            url: '/invoices/bonuses',
            icon: Gift,
            badge: pendingBonusCount > 0 ? pendingBonusCount : undefined,
        },
        {
            title: 'الرصيد',
            url: '/invoices/credits',
            icon: Wallet,
            badge: pendingCreditCount > 0 ? pendingCreditCount : undefined,
        },
        { title: 'الموظفين', url: '/employees', icon: Users },
    ];

    const navItems = auth.user?.is_admin ? [...mainNavItems, ...adminNavItems] : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset" side="right" className="border-sidebar-border/60">
            <SidebarHeader className="border-sidebar-border/60 border-b p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            tooltip={name}
                            className="rounded-xl group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0"
                        >
                            <Link href="/invoices/active" prefetch className="flex items-center group-data-[collapsible=icon]:justify-center">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-2 py-4">
                <NavMain items={navItems} label="القائمة الرئيسية" />
            </SidebarContent>

            <SidebarFooter className="border-sidebar-border/60 space-y-2 overflow-visible border-t p-3">
                <button
                    type="button"
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    title={isDark ? 'الوضع الفاتح' : 'الوضع الداكن'}
                    className="text-muted-foreground hover:bg-sidebar-accent hover:text-foreground flex w-full items-center justify-center gap-2 rounded-xl border border-sidebar-border/60 px-3 py-2 text-sm transition-colors group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:p-0"
                >
                    {isDark ? <Sun className="size-4 text-amber-500" /> : <Moon className="size-4 text-primary" />}
                    <span className="group-data-[collapsible=icon]:hidden">{isDark ? 'الوضع الفاتح' : 'الوضع الداكن'}</span>
                </button>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
