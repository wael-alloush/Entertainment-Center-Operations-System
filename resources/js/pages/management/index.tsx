import { FlashAlert } from '@/components/raad/flash-alert';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cssColorToHex } from '@/lib/css-color';
import { cn } from '@/lib/utils';
import { ManagedEmployee, ManagedSessionDuration, WorkSchedule } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock, Pencil, Plus, Settings2, Timer, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface ManagementPageProps {
    sessionDurations: ManagedSessionDuration[];
    employees: ManagedEmployee[];
    workSchedule: WorkSchedule;
}

type Tab = 'sessions' | 'employees';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'إدارة النظام', href: '/management' },
];

const tabs: { id: Tab; label: string; icon: typeof Timer }[] = [
    { id: 'sessions', label: 'أنواع الجلسات', icon: Timer },
    { id: 'employees', label: 'إدارة الموظفين', icon: Users },
];

const DEFAULT_SESSION_COLOR = '#6366f1';

const defaultSessionForm = {
    name: '',
    duration_minutes: 60 as number | null,
    color: DEFAULT_SESSION_COLOR,
    sort_order: 1,
    is_active: true,
    is_open: false,
};

function buildSessionPayload(data: typeof defaultSessionForm) {
    return {
        name: data.name,
        duration_minutes: data.is_open ? null : data.duration_minutes,
        color: cssColorToHex(data.color),
        sort_order: data.sort_order,
        is_active: data.is_active,
    };
}

function SessionBadge({ name, color }: { name: string; color: string }) {
    return (
        <Badge
            variant="outline"
            className="border-0 font-medium text-white shadow-sm"
            style={{ backgroundColor: color, boxShadow: `${color}33 0 2px 8px` }}
        >
            {name}
        </Badge>
    );
}

function formatDuration(minutes: number | null, isOpen: boolean): string {
    if (isOpen || minutes === null) {
        return 'مفتوح';
    }

    return `${minutes} دقيقة`;
}

export default function ManagementPage({ sessionDurations, employees, workSchedule }: ManagementPageProps) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const [activeTab, setActiveTab] = useState<Tab>('sessions');
    const [sessionDialogOpen, setSessionDialogOpen] = useState(false);
    const [employeeDialogOpen, setEmployeeDialogOpen] = useState(false);
    const [editingSession, setEditingSession] = useState<ManagedSessionDuration | null>(null);
    const [editingEmployee, setEditingEmployee] = useState<ManagedEmployee | null>(null);

    const sessionForm = useForm(defaultSessionForm);
    const employeeForm = useForm({ name: '', is_active: true });
    const scheduleForm = useForm({ start_time: workSchedule.start_time });

    const openCreateSession = () => {
        setEditingSession(null);
        sessionForm.reset();
        sessionForm.setData(defaultSessionForm);
        setSessionDialogOpen(true);
    };

    const openEditSession = (session: ManagedSessionDuration) => {
        setEditingSession(session);
        sessionForm.setData({
            name: session.name,
            duration_minutes: session.duration_minutes,
            color: cssColorToHex(session.color),
            sort_order: session.sort_order,
            is_active: session.is_active,
            is_open: session.is_open,
        });
        setSessionDialogOpen(true);
    };

    const openCreateEmployee = () => {
        setEditingEmployee(null);
        employeeForm.reset();
        employeeForm.setData({ name: '', is_active: true });
        setEmployeeDialogOpen(true);
    };

    const openEditEmployee = (employee: ManagedEmployee) => {
        setEditingEmployee(employee);
        employeeForm.setData({
            name: employee.name,
            is_active: employee.is_active,
        });
        setEmployeeDialogOpen(true);
    };

    const submitSession = (event: FormEvent) => {
        event.preventDefault();

        const payload = buildSessionPayload(sessionForm.data);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setSessionDialogOpen(false);

                if (!editingSession) {
                    sessionForm.reset();
                }
            },
            onFinish: () => sessionForm.transform((data) => data),
        };

        sessionForm.transform(() => payload);

        if (editingSession) {
            sessionForm.patch(route('management.session-durations.update', editingSession.id), options);

            return;
        }

        sessionForm.post(route('management.session-durations.store'), options);
    };

    const submitEmployee = (event: FormEvent) => {
        event.preventDefault();

        if (editingEmployee) {
            employeeForm.patch(route('management.employees.update', editingEmployee.id), {
                onSuccess: () => setEmployeeDialogOpen(false),
            });

            return;
        }

        employeeForm.post(route('management.employees.store'), {
            onSuccess: () => {
                setEmployeeDialogOpen(false);
                employeeForm.reset();
            },
        });
    };

    const submitSchedule = (event: FormEvent) => {
        event.preventDefault();
        scheduleForm.patch(route('management.work-schedule.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إدارة النظام" />
            <FlashAlert message={flash?.success} />

            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <span className="raad-icon-chip bg-primary/12 text-primary">
                        <Settings2 className="size-5" strokeWidth={2.25} />
                    </span>
                    <h1 className="text-2xl font-bold tracking-tight">إدارة النظام</h1>
                </div>
                <p className="text-muted-foreground text-sm">التحكم بأنواع الجلسات وإدارة الموظفين ووقت الدوام</p>
            </div>

            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    const tabIconClass =
                        tab.id === 'sessions' ? 'text-violet-600 dark:text-violet-400' : 'text-sky-600 dark:text-sky-400';

                    return (
                        <Button
                            key={tab.id}
                            type="button"
                            variant={isActive ? 'default' : 'outline'}
                            onClick={() => setActiveTab(tab.id)}
                            className={cn('rounded-xl', !isActive && 'border-border/70')}
                        >
                            <tab.icon className={cn('size-4', isActive ? 'text-primary-foreground' : tabIconClass)} />
                            {tab.label}
                        </Button>
                    );
                })}
            </div>

            {activeTab === 'sessions' && (
                <Card className="raad-card-surface">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>أنواع الجلسات</CardTitle>
                            <CardDescription>تحديد مدة كل جلسة بالدقائق وترتيب العرض في النظام</CardDescription>
                        </div>
                        <Button type="button" onClick={openCreateSession}>
                            <Plus className="size-4" />
                            إضافة جلسة
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="raad-table-head">
                                    <tr>
                                        <th className="px-4 py-3 text-right font-medium">الاسم</th>
                                        <th className="px-4 py-3 text-right font-medium">المدة</th>
                                        <th className="px-4 py-3 text-right font-medium">الترتيب</th>
                                        <th className="px-4 py-3 text-right font-medium">الحالة</th>
                                        <th className="px-4 py-3 text-right font-medium">الفواتير</th>
                                        <th className="px-4 py-3 text-right font-medium">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sessionDurations.map((session) => (
                                        <tr key={session.id} className="border-t">
                                            <td className="px-4 py-3">
                                                <SessionBadge name={session.name} color={session.color} />
                                            </td>
                                            <td className="px-4 py-3">{formatDuration(session.duration_minutes, session.is_open)}</td>
                                            <td className="px-4 py-3">{session.sort_order}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={session.is_active ? 'default' : 'secondary'}>
                                                    {session.is_active ? 'مفعّل' : 'معطّل'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">{session.invoices_count}</td>
                                            <td className="px-4 py-3">
                                                <Button type="button" variant="ghost" size="sm" onClick={() => openEditSession(session)}>
                                                    <Pencil className="size-4" />
                                                    تعديل
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'employees' && (
                <Card className="raad-card-surface">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>إدارة الموظفين</CardTitle>
                            <CardDescription>
                                إضافة وتعديل الموظفين وتحديد وقت بدء الدوام لحساب التأخير عند تسجيل الحضور
                            </CardDescription>
                        </div>
                        <Button type="button" onClick={openCreateEmployee}>
                            <Plus className="size-4" />
                            إضافة موظف
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <form onSubmit={submitSchedule} className="flex flex-wrap items-end gap-4 rounded-xl border border-border/60 bg-muted/20 p-4">
                            <div className="min-w-[12rem] flex-1 space-y-2">
                                <Label htmlFor="start_time" className="flex items-center gap-1.5">
                                    <Clock className="text-muted-foreground size-3.5" />
                                    وقت بدء الدوام
                                </Label>
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={scheduleForm.data.start_time}
                                    onChange={(event) => scheduleForm.setData('start_time', event.target.value)}
                                    required
                                />
                                <InputError message={scheduleForm.errors.start_time} />
                                <p className="text-muted-foreground text-xs">الوقت الحالي: {workSchedule.start_time}</p>
                            </div>
                            <Button type="submit" size="sm" disabled={scheduleForm.processing}>
                                حفظ وقت الدوام
                            </Button>
                        </form>

                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="raad-table-head">
                                    <tr>
                                        <th className="px-4 py-3 text-right font-medium">الاسم</th>
                                        <th className="px-4 py-3 text-right font-medium">الحالة</th>
                                        <th className="px-4 py-3 text-right font-medium">سجلات الحضور</th>
                                        <th className="px-4 py-3 text-right font-medium">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.map((employee) => (
                                        <tr key={employee.id} className="border-t">
                                            <td className="px-4 py-3 font-medium">{employee.name}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={employee.is_active ? 'default' : 'secondary'}>
                                                    {employee.is_active ? 'يعمل حالياً' : 'معطّل / مفصول'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-primary"
                                                    disabled={employee.attendance_records_count === 0}
                                                    onClick={() => openAttendanceHistory(employee)}
                                                >
                                                    <History className="size-4" />
                                                    {employee.attendance_records_count}
                                                </Button>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => openEditEmployee(employee)}>
                                                        <Pencil className="size-4" />
                                                        تعديل
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Dialog open={sessionDialogOpen} onOpenChange={setSessionDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingSession ? 'تعديل نوع الجلسة' : 'إضافة نوع جلسة'}</DialogTitle>
                        <DialogDescription>حدد اسم الجلسة ومدتها بالدقائق أو اجعلها مفتوحة بدون وقت محدد</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitSession} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="session_name">اسم الجلسة</Label>
                            <Input
                                id="session_name"
                                value={sessionForm.data.name}
                                onChange={(event) => sessionForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={sessionForm.errors.name} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_open"
                                checked={sessionForm.data.is_open}
                                onCheckedChange={(checked) => {
                                    const isOpen = checked === true;
                                    sessionForm.setData({
                                        ...sessionForm.data,
                                        is_open: isOpen,
                                        duration_minutes: isOpen ? null : (sessionForm.data.duration_minutes ?? 60),
                                    });
                                }}
                            />
                            <Label htmlFor="is_open">جلسة مفتوحة (بدون مدة محددة)</Label>
                        </div>

                        {!sessionForm.data.is_open && (
                            <div className="grid gap-2">
                                <Label htmlFor="duration_minutes">المدة بالدقائق</Label>
                                <Input
                                    id="duration_minutes"
                                    type="number"
                                    min={1}
                                    max={1440}
                                    value={sessionForm.data.duration_minutes ?? ''}
                                    onChange={(event) =>
                                        sessionForm.setData('duration_minutes', event.target.value ? Number(event.target.value) : null)
                                    }
                                />
                                <InputError message={sessionForm.errors.duration_minutes} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="color">لون العرض</Label>
                            <div className="flex items-center gap-3">
                                <Input
                                    id="color"
                                    type="color"
                                    value={sessionForm.data.color}
                                    onChange={(event) => sessionForm.setData('color', event.target.value.toLowerCase())}
                                    className="h-11 w-16 shrink-0 cursor-pointer rounded-lg border p-1"
                                />
                                <SessionBadge name="معاينة" color={sessionForm.data.color} />
                                <span className="text-muted-foreground font-mono text-xs">{sessionForm.data.color}</span>
                            </div>
                            <InputError message={sessionForm.errors.color} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sort_order">ترتيب العرض</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                min={0}
                                max={255}
                                value={sessionForm.data.sort_order}
                                onChange={(event) => sessionForm.setData('sort_order', Number(event.target.value))}
                                required
                            />
                            <InputError message={sessionForm.errors.sort_order} />
                        </div>

                        {editingSession && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={sessionForm.data.is_active}
                                    onCheckedChange={(checked) => sessionForm.setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">مفعّل في النظام</Label>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setSessionDialogOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={sessionForm.processing}>
                                {editingSession ? 'حفظ التعديلات' : 'إضافة'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={employeeDialogOpen} onOpenChange={setEmployeeDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingEmployee ? 'تعديل الموظف' : 'إضافة موظف'}</DialogTitle>
                        <DialogDescription>
                            {editingEmployee
                                ? 'إلغاء تفعيل الموظف يحتفظ بسجلات حضوره السابقة ويمنع ظهوره لتسجيل حضور جديد'
                                : 'الموظفون هنا مخصصون لتسجيل الحضور والانصراف اليومي'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEmployee} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="employee_name">اسم الموظف</Label>
                            <Input
                                id="employee_name"
                                value={employeeForm.data.name}
                                onChange={(event) => employeeForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={employeeForm.errors.name} />
                        </div>

                        {editingEmployee && (
                            <div className="space-y-2 rounded-xl border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="employee_active"
                                        checked={employeeForm.data.is_active}
                                        onCheckedChange={(checked) => employeeForm.setData('is_active', checked === true)}
                                    />
                                    <Label htmlFor="employee_active">يعمل حالياً ويظهر في صفحة الحضور</Label>
                                </div>
                                {!employeeForm.data.is_active && (
                                    <p className="text-muted-foreground text-xs">
                                        سيتم إخفاء الموظف من صفحة الحضور مع الإبقاء على {editingEmployee.attendance_records_count} سجل
                                        حضور سابق
                                    </p>
                                )}
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEmployeeDialogOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={employeeForm.processing}>
                                {editingEmployee ? 'حفظ التعديلات' : 'إضافة'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}
