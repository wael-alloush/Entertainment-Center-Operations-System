import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type ManagementTab, managementPageIcon, managementTabs } from '@/layouts/management/tabs';
import { type ReactNode } from 'react';

interface ManagementLayoutProps {
    activeTab: ManagementTab;
    onTabChange: (tab: ManagementTab) => void;
    children: ReactNode;
}

export default function ManagementLayout({ activeTab, onTabChange, children }: ManagementLayoutProps) {
    const PageIcon = managementPageIcon;

    return (
        <div className="space-y-4">
            <header className="space-y-1">
                <div className="flex items-center gap-2">
                    <span className="raad-icon-chip bg-primary/12 text-primary">
                        <PageIcon className="size-5" strokeWidth={2.25} />
                    </span>
                    <h1 className="raad-page-title">إدارة النظام</h1>
                </div>
                <p className="text-muted-foreground text-sm">التحكم بأنواع الجلسات والموظفين والمستخدمين ووقت الدوام</p>
            </header>

            <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                {managementTabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    const TabIcon = tab.icon;

                    return (
                        <Button
                            key={tab.id}
                            type="button"
                            variant={isActive ? 'default' : 'outline'}
                            onClick={() => onTabChange(tab.id)}
                            className={cn('shrink-0 rounded-xl', !isActive && 'border-border/70')}
                        >
                            <TabIcon className={cn('size-4', isActive ? 'text-primary-foreground' : tab.iconClass)} />
                            {tab.label}
                        </Button>
                    );
                })}
            </div>

            <div className="min-w-0">{children}</div>
        </div>
    );
}
