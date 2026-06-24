import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { EmployeeAttendanceHistoryPayload, EmployeeAttendanceRecord } from '@/types/domain';
import { Clock3, CalendarOff, History, LogIn, LogOut, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface EmployeeAttendanceDialogProps {
    employeeId: number | null;
    employeeName?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function lateBadgeClass(label: string): string {
    if (label.startsWith('متأخر')) {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }

    if (label.startsWith('حضر قبل')) {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (label === 'في الوقت') {
        return 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function AttendanceRecordCard({ record }: { record: EmployeeAttendanceRecord }) {
    const isDayOff = record.is_day_off === true;
    const hasCheckout = record.checked_out_at !== '—';

    if (isDayOff) {
        return (
            <article className="rounded-lg border border-dashed border-muted-foreground/30 bg-muted/25 px-3 py-2">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold">
                        <span dir="ltr">{record.work_date}</span>
                        {record.work_day_name && <span className="text-muted-foreground mx-1">·</span>}
                        <span>{record.work_day_name}</span>
                    </p>
                    <Badge variant="outline" className="h-5 gap-0.5 border-muted-foreground/30 bg-muted px-1.5 text-[10px] text-muted-foreground">
                        <CalendarOff className="size-2.5" />
                        إجازة
                    </Badge>
                </div>
            </article>
        );
    }

    return (
        <article className="rounded-lg border bg-card px-3 py-2">
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">
                    <span dir="ltr">{record.work_date}</span>
                    {record.work_day_name && <span className="text-muted-foreground mx-1">·</span>}
                    <span>{record.work_day_name}</span>
                </p>
                {record.late_label !== '—' && (
                    <Badge variant="outline" className={cn('h-5 gap-0.5 px-1.5 text-[10px]', lateBadgeClass(record.late_label))}>
                        <Clock3 className="size-2.5" />
                        {record.late_label}
                    </Badge>
                )}
            </div>

            <div className="grid grid-cols-2 gap-1.5">
                <div className="flex items-center justify-between gap-1 rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2 py-1">
                    <span className="text-muted-foreground flex items-center gap-0.5 text-[10px]">
                        <LogIn className="size-2.5 text-emerald-600" />
                        حضور
                    </span>
                    <span className="text-xs font-bold text-emerald-700 dark:text-emerald-300" dir="ltr">
                        {record.checked_in_at}
                    </span>
                </div>
                <div
                    className={cn(
                        'flex items-center justify-between gap-1 rounded-md border px-2 py-1',
                        hasCheckout
                            ? 'border-rose-500/20 bg-rose-500/10'
                            : 'border-dashed border-muted-foreground/25 bg-muted/40',
                    )}
                >
                    <span className="text-muted-foreground flex items-center gap-0.5 text-[10px]">
                        <LogOut className={cn('size-2.5', hasCheckout ? 'text-rose-600' : 'text-muted-foreground')} />
                        انصراف
                    </span>
                    <span
                        className={cn(
                            'text-xs font-bold',
                            hasCheckout ? 'text-rose-700 dark:text-rose-300' : 'text-muted-foreground',
                        )}
                        dir="ltr"
                    >
                        {hasCheckout ? record.checked_out_at : '—'}
                    </span>
                </div>
            </div>
        </article>
    );
}

export function EmployeeAttendanceDialog({ employeeId, employeeName, open, onOpenChange }: EmployeeAttendanceDialogProps) {
    const [loading, setLoading] = useState(false);
    const [payload, setPayload] = useState<EmployeeAttendanceHistoryPayload | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadHistory = useCallback(async (id: number) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(route('employees.attendance-history', id), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('failed');
            }

            const data = (await response.json()) as EmployeeAttendanceHistoryPayload;
            setPayload(data);
        } catch {
            setError('تعذر تحميل سجل الحضور.');
            setPayload(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (open && employeeId) {
            loadHistory(employeeId);
        } else {
            setPayload(null);
            setError(null);
        }
    }, [open, employeeId, loadHistory]);

    const titleName = payload?.employee_name ?? employeeName ?? 'الموظف';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="raad-dialog-surface max-h-[90vh] gap-0 overflow-hidden p-0 md:max-w-xl [&>button.absolute]:hidden">
                <DialogHeader className="border-b bg-muted/30 px-4 py-4 text-right">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <History className="text-primary size-5 shrink-0" />
                                <DialogTitle className="truncate text-lg">سجل حضور {titleName}</DialogTitle>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {payload
                                    ? `${payload.records_count} يوم مسجّل${payload.records_count > payload.records.length ? ` · يعرض آخر ${payload.records.length}` : ''}`
                                    : 'جاري التحميل...'}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground shrink-0"
                            onClick={() => onOpenChange(false)}
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-2 overflow-y-auto p-3">
                    {loading &&
                        Array.from({ length: 4 }).map((_, index) => (
                            <Skeleton key={index} className="h-14 w-full rounded-lg" />
                        ))}

                    {!loading && error && <p className="text-muted-foreground py-12 text-center text-sm">{error}</p>}

                    {!loading && !error && payload && payload.records.length === 0 && (
                        <div className="py-12 text-center">
                            <History className="text-muted-foreground/40 mx-auto mb-3 size-10" />
                            <p className="text-muted-foreground text-sm">لا توجد سجلات حضور لهذا الموظف بعد</p>
                        </div>
                    )}

                    {!loading &&
                        !error &&
                        payload?.records.map((record) => <AttendanceRecordCard key={record.id} record={record} />)}
                </div>
            </DialogContent>
        </Dialog>
    );
}
