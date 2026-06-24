import { SidebarMenuButton, useSidebar } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { type SharedData, type User } from '@/types';
import { CloseButton, Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Settings } from 'lucide-react';

function UserPopoverMenu({ user }: { user: User }) {
    const { state, isMobile } = useSidebar();
    const isCollapsed = !isMobile && state === 'collapsed';
    const cleanup = useMobileNavigation();

    return (
        <Popover className="relative w-full">
            <PopoverButton
                className={cn(
                    'text-sidebar-accent-foreground data-[open]:bg-sidebar-accent flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-start text-sm outline-hidden ring-sidebar-ring transition-[width,height,padding] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2',
                    isCollapsed ? 'size-10 justify-center p-0' : 'h-12',
                )}
            >
                <UserInfo user={user} />
                {!isCollapsed && <ChevronsUpDown className="ms-auto size-4 shrink-0" />}
            </PopoverButton>

            <PopoverPanel
                anchor={isCollapsed ? 'left start' : 'top start'}
                transition
                className="z-[200] min-w-56 rounded-lg border bg-popover p-1 text-popover-foreground shadow-md [--anchor-gap:8px] transition duration-150 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <div className="px-2 py-2">
                    <UserInfo user={user} showEmail />
                </div>

                <div className="bg-border/60 mx-1 my-1 h-px" />

                <CloseButton
                    as={Link}
                    href={route('profile.edit')}
                    prefetch
                    onClick={cleanup}
                    className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors"
                >
                    <Settings className="size-4 shrink-0" />
                    الإعدادات
                </CloseButton>

                <CloseButton
                    as={Link}
                    method="post"
                    href={route('logout')}
                    onClick={cleanup}
                    className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors"
                >
                    <LogOut className="size-4 shrink-0" />
                    تسجيل الخروج
                </CloseButton>
            </PopoverPanel>
        </Popover>
    );
}

function MobileUserMenu() {
    const { auth } = usePage<SharedData>().props;
    const cleanup = useMobileNavigation();

    return (
        <div className="overflow-hidden rounded-xl border border-sidebar-border/60 bg-sidebar-accent/25">
            <div className="border-sidebar-border/60 border-b px-3 py-3">
                <UserInfo user={auth.user} />
            </div>

            <div className="space-y-0.5 p-1.5">
                <SidebarMenuButton asChild className="h-11 rounded-lg px-2">
                    <Link
                        className="flex w-full items-center gap-3"
                        href={route('profile.edit')}
                        prefetch
                        onClick={cleanup}
                    >
                        <span className="raad-icon-chip size-9 bg-sidebar-accent/80 text-muted-foreground">
                            <Settings className="size-[1.125rem]" strokeWidth={2.5} />
                        </span>
                        <span className="font-medium">الإعدادات</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuButton
                    asChild
                    className="h-11 rounded-lg px-2 text-rose-700 hover:bg-rose-500/10 hover:text-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/15 dark:hover:text-rose-200"
                >
                    <Link
                        className="flex w-full items-center gap-3"
                        method="post"
                        href={route('logout')}
                        as="button"
                        onClick={cleanup}
                    >
                        <span className="raad-icon-chip size-9 bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <LogOut className="size-[1.125rem]" strokeWidth={2.5} />
                        </span>
                        <span className="font-medium">تسجيل الخروج</span>
                    </Link>
                </SidebarMenuButton>
            </div>
        </div>
    );
}

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const { isMobile } = useSidebar();

    if (isMobile) {
        return <MobileUserMenu />;
    }

    return <UserPopoverMenu user={auth.user} />;
}
