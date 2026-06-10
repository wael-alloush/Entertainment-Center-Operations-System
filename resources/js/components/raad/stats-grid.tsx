import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { DashboardStats } from '@/types/domain';
import { Baby, CalendarDays, CheckCircle2, Users, type LucideIcon } from 'lucide-react';

interface StatsGridProps {
    stats: DashboardStats;
}

const statItems: Array<{
    key: keyof DashboardStats;
    title: string;
    description: string;
    icon: LucideIcon;
    iconClass: string;
    format: (value: number) => string;
}> = [
    {
        key: 'completed_month',
        title: 'فواتير الشهر',
        description: 'مكتملة هذا الشهر',
        icon: CalendarDays,
        iconClass: 'bg-violet-500/12 text-violet-600 dark:text-violet-400',
        format: (value: number) => String(value),
    },
    {
        key: 'active_children',
        title: 'الأطفال النشطون',
        description: 'داخل المركز الآن',
        icon: Baby,
        iconClass: 'bg-rose-500/12 text-rose-600 dark:text-rose-400',
        format: (value: number) => String(value),
    },
    {
        key: 'total_customers',
        title: 'إجمالي العملاء',
        description: 'عملاء مسجلون',
        icon: Users,
        iconClass: 'bg-sky-500/12 text-sky-600 dark:text-sky-400',
        format: (value: number) => String(value),
    },
    {
        key: 'completed_today',
        title: 'فواتير اليوم',
        description: 'مكتملة اليوم',
        icon: CheckCircle2,
        iconClass: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
        format: (value: number) => String(value),
    },
];

export function StatsGrid({ stats }: StatsGridProps) {
    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            {statItems.map((stat) => (
                <Card key={stat.key} className="raad-card-surface">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                        <div className={cn('raad-icon-chip', stat.iconClass)}>
                            <stat.icon className="size-[1.125rem]" strokeWidth={2.25} />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold tracking-tight">{stat.format(stats[stat.key])}</div>
                        <p className="text-muted-foreground mt-1 text-xs">{stat.description}</p>
                        {stat.key === 'active_children' && stats.active_invoices > 0 && (
                            <p className="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{stats.active_invoices} فاتورة نشطة</p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
