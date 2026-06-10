import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { auth } = usePage<SharedData>().props;
    const now = new Date().toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <header className="border-border/60 bg-background/80 flex h-16 shrink-0 items-center justify-between gap-4 border-b px-4 backdrop-blur-sm md:px-6">
            <div className="flex items-center gap-3">
                <SidebarTrigger className="rounded-xl" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="hidden items-center gap-3 sm:flex">
                <span className="text-muted-foreground text-xs">{now}</span>
                <Badge variant="secondary" className="font-normal">
                    {auth.user.role_label}
                </Badge>
            </div>
        </header>
    );
}
