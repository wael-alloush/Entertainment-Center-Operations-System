import { EmployeeAttendanceDialog } from '@/components/raad/employee-attendance-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatLateMinutes } from '@/lib/format-time';
import { cn } from '@/lib/utils';
import { EmployeeAttendance } from '@/types/domain';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { CalendarOff, Clock3, History, LoaderCircle, LogIn, LogOut, UserRound } from 'lucide-react';
import { useState } from 'react';

interface EmployeeCardProps {
    employee: EmployeeAttendance;
    canRecordAttendance?: boolean;
}

export function EmployeeCard({ employee, canRecordAttendance = true }: EmployeeCardProps) {
    const { auth } = usePage<SharedData>().props;
    const [historyOpen, setHistoryOpen] = useState(false);
    const [dayOffLoading, setDayOffLoading] = useState(false);
    const lateLabel = formatLateMinutes(employee.late_minutes);
    const canViewHistory = auth.user.is_admin;
    const canManageDayOff = auth.user.is_admin && employee.can_manage_day_off;
    const isDayOff = employee.is_day_off;

    const handleCheckIn = () => router.post(route('employees.check-in', employee.id));
    const handleCheckOut = () => router.post(route('employees.check-out', employee.id));

    const toggleDayOff = () => {
        setDayOffLoading(true);

        if (employee.is_day_off) {
            router.delete(route('employees.unmark-day-off', employee.id), {
                preserveScroll: true,
                onFinish: () => setDayOffLoading(false),
            });

            return;
        }

        router.post(route('employees.mark-day-off', employee.id), {}, {
            preserveScroll: true,
            onFinish: () => setDayOffLoading(false),
        });
    };

    return (
        <>
            <Card className={cn('raad-card-surface relative', isDayOff && 'border-dashed border-muted-foreground/30 bg-muted/20')}>
                {canViewHistory && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-primary absolute top-3 left-3 size-8 shrink-0"
                        disabled={(employee.attendance_records_count ?? 0) === 0}
                        title="سجل الحضور"
                        onClick={() => setHistoryOpen(true)}
                    >
                        <History className="size-4" />
                    </Button>
                )}
                {auth.user.is_admin && (canManageDayOff || isDayOff) && (
                    <Button
                        type="button"
                        variant={isDayOff ? 'secondary' : 'outline'}
                        size="sm"
                        className="absolute top-3 right-3 h-8 shrink-0 gap-1 px-2 text-xs"
                        disabled={dayOffLoading || (!canManageDayOff && !isDayOff)}
                        onClick={toggleDayOff}
                    >
                        {dayOffLoading ? (
                            <LoaderCircle className="size-3.5 animate-spin" />
                        ) : (
                            <CalendarOff className="size-3.5" />
                        )}
                        {isDayOff ? 'إلغاء الإجازة' : 'إجازة'}
                    </Button>
                )}
                <CardHeader className="px-4 pt-12 pb-3 sm:pt-6">
                    <CardTitle className="flex items-center justify-center gap-2 text-center text-base sm:text-lg">
                        <span className="bg-primary/10 text-primary raad-icon-chip size-8">
                            <UserRound className="size-4" />
                        </span>
                        {employee.name}
                    </CardTitle>
                    {isDayOff ? (
                        <div className="flex justify-center">
                            <Badge variant="outline" className="gap-1 border-sky-500/50 bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                <CalendarOff className="size-3" />
                                إجازة
                            </Badge>
                        </div>
                    ) : (
                        lateLabel &&
                        employee.has_checked_in && (
                            <div className="flex justify-center">
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        'gap-1',
                                        employee.late_minutes && employee.late_minutes > 0
                                            ? employee.late_minutes < 5
                                                ? 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                : 'border-destructive/50 bg-destructive/10 text-destructive'
                                            : 'border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                    )}
                                >
                                    <Clock3 className="size-3" />
                                    {lateLabel}
                                </Badge>
                            </div>
                        )
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-center">
                            <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
                                <LogIn className="size-3 text-emerald-600" />
                                الحضور
                            </p>
                            <p className="text-lg font-bold text-emerald-700 dark:text-emerald-300">
                                {isDayOff ? '—' : employee.check_in}
                            </p>
                        </div>
                        <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-center">
                            <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
                                <LogOut className="size-3 text-rose-600" />
                                الانصراف
                            </p>
                            <p className="text-lg font-bold text-rose-700 dark:text-rose-300">
                                {isDayOff ? '—' : employee.check_out}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            disabled={!canRecordAttendance || isDayOff || employee.has_checked_in}
                            onClick={handleCheckIn}
                            className="bg-emerald-600 shadow-sm shadow-emerald-600/25 hover:bg-emerald-600/90"
                        >
                            <LogIn className="size-4" />
                            حضور
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={!canRecordAttendance || isDayOff || !employee.has_checked_in || employee.has_checked_out}
                            onClick={handleCheckOut}
                            className="shadow-sm shadow-destructive/20"
                        >
                            <LogOut className="size-4" />
                            انصراف
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {canViewHistory && (
                <EmployeeAttendanceDialog
                    employeeId={employee.id}
                    employeeName={employee.name}
                    open={historyOpen}
                    onOpenChange={setHistoryOpen}
                />
            )}
        </>
    );
}
