import { Skeleton } from '@/components/ui/skeleton';

export function ChartSkeleton() {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Skeleton className="h-6 w-48" />
                <Skeleton className="h-4 w-64" />
            </div>
            <Skeleton className="h-80 w-full rounded-xl" />
        </div>
    );
}

export function PieChartSkeleton() {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Skeleton className="h-6 w-40" />
                <Skeleton className="h-4 w-52" />
            </div>
            <Skeleton className="mx-auto h-48 w-48 rounded-full" />
            <div className="space-y-2">
                {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-4 w-full" />
                ))}
            </div>
        </div>
    );
}
