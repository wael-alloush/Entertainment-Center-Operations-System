import { StatsGrid } from '@/components/raad/stats-grid';
import { ChartSkeleton, PieChartSkeleton } from '@/components/raad/chart-skeleton';
import AppLayout from '@/layouts/app-layout';
import { ChartSessionDuration, DashboardStats, SessionDistributionItem } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Deferred, Head } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { lazy, Suspense } from 'react';

const SessionsChart = lazy(() =>
    import('@/components/raad/sessions-chart').then((module) => ({ default: module.SessionsChart })),
);
const SessionDistributionChart = lazy(() =>
    import('@/components/raad/session-distribution-chart').then((module) => ({ default: module.SessionDistributionChart })),
);

interface DashboardProps {
    stats: DashboardStats;
    monthlyChart?: Record<string, string | number>[];
    sessionDistribution?: SessionDistributionItem[];
    sessionDurations?: ChartSessionDuration[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'لوحة التحكم', href: '/dashboard' }];

export default function Dashboard({ stats, monthlyChart, sessionDistribution, sessionDurations }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="لوحة التحكم" />
            <div className="space-y-2">
                <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <span className="raad-icon-chip bg-primary/12 text-primary">
                        <LayoutDashboard className="size-5" strokeWidth={2.25} />
                    </span>
                    لوحة التحكم
                </h1>
                <p className="text-muted-foreground text-sm">نظرة عامة على الفواتير وحضور الموظفين والمكافآت</p>
            </div>
            <StatsGrid stats={stats} />
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Deferred data={['monthlyChart', 'sessionDurations']} fallback={<ChartSkeleton />}>
                        {monthlyChart && sessionDurations && (
                            <Suspense fallback={<ChartSkeleton />}>
                                <SessionsChart data={monthlyChart} sessionDurations={sessionDurations} />
                            </Suspense>
                        )}
                    </Deferred>
                </div>
                <Deferred data="sessionDistribution" fallback={<PieChartSkeleton />}>
                    {sessionDistribution && (
                        <Suspense fallback={<PieChartSkeleton />}>
                            <SessionDistributionChart data={sessionDistribution} />
                        </Suspense>
                    )}
                </Deferred>
            </div>
        </AppLayout>
    );
}
