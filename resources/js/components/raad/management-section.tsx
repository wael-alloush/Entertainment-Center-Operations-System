import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface ManagementSectionProps {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}

export function ManagementSection({ title, description, action, children, className }: ManagementSectionProps) {
    return (
        <section className={cn('space-y-3', className)}>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {description && <p className="text-muted-foreground text-xs leading-relaxed">{description}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
