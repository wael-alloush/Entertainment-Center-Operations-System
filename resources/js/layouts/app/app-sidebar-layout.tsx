import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AssistantChat } from '@/components/raad/assistant-chat';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 sm:gap-6 sm:p-4 md:p-6">{children}</div>
            </AppContent>
            <AssistantChat />
        </AppShell>
    );
}
