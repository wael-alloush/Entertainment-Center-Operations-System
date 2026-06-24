import { useConfirm } from '@/components/raad/confirm-dialog';
import {
    RaadDesktopTable,
    RaadMobileActions,
    RaadMobileBody,
    RaadMobileCard,
    RaadMobileCardHeader,
    RaadMobileChips,
    RaadMobileGrid,
    RaadMobileStack,
    RaadMobileStat,
    RaadTableEmpty,
} from '@/components/raad/raad-responsive-table';
import { ManagementSection } from '@/components/raad/management-section';
import { RaadTimeInput } from '@/components/raad/raad-time-input';
import { EmployeeAttendanceDialog } from '@/components/raad/employee-attendance-dialog';
import { FlashAlert } from '@/components/raad/flash-alert';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
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
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import ManagementLayout from '@/layouts/management/layout';
import {
    managementTabFromHash,
    type ManagementTab,
} from '@/layouts/management/tabs';
import { cssColorToHex } from '@/lib/css-color';
import { formatTimeString12 } from '@/lib/format-time';
import {
    AttendanceDeviceRestrictionSettings,
    BonusProgramSettings,
    DeviceTrustSettings,
    InvoiceCleanupStats,
    ManagedEmployee,
    ManagedSessionDuration,
    ManagedTrustedDevice,
    ManagedUser,
    PendingDeviceCode,
    WorkSchedule,
} from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Archive, CalendarDays, Clock, Gift, History, MonitorSmartphone, Pencil, Plus, Timer, Trash2, Users } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface ManagementPageProps {
    sessionDurations: ManagedSessionDuration[];
    employees: ManagedEmployee[];
    users: ManagedUser[];
    deviceTrust: DeviceTrustSettings;
    attendanceDeviceRestriction: AttendanceDeviceRestrictionSettings;
    trustedDevices: ManagedTrustedDevice[];
    pendingDeviceCodes: PendingDeviceCode[];
    workSchedule: WorkSchedule;
    bonusProgram: BonusProgramSettings;
    invoiceCleanup: InvoiceCleanupStats;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'إدارة النظام', href: '/management' }];

const defaultUserForm = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'reception' as ManagedUser['role'],
    is_active: true,
};

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

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ar-SA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function ManagementPage({
    sessionDurations,
    employees,
    users,
    deviceTrust,
    attendanceDeviceRestriction,
    trustedDevices,
    pendingDeviceCodes,
    workSchedule,
    bonusProgram,
    invoiceCleanup,
}: ManagementPageProps) {
    const confirm = useConfirm();
    const { flash, auth } = usePage<{
        flash: {
            success?: string;
            generated_device_code?: string;
            generated_device_code_expires_at?: string;
        };
        auth: { user: { id: number } };
    }>().props;
    const [activeTab, setActiveTab] = useState<ManagementTab>(() => managementTabFromHash(window.location.hash));
    const [sessionDialogOpen, setSessionDialogOpen] = useState(false);
    const [employeeDialogOpen, setEmployeeDialogOpen] = useState(false);
    const [userDialogOpen, setUserDialogOpen] = useState(false);
    const [deviceDialogOpen, setDeviceDialogOpen] = useState(false);
    const [codeDialogOpen, setCodeDialogOpen] = useState(false);
    const [generatedCodeOpen, setGeneratedCodeOpen] = useState(false);
    const [editingSession, setEditingSession] = useState<ManagedSessionDuration | null>(null);
    const [editingEmployee, setEditingEmployee] = useState<ManagedEmployee | null>(null);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [editingDevice, setEditingDevice] = useState<ManagedTrustedDevice | null>(null);
    const [generatedCode, setGeneratedCode] = useState<{ code: string; expiresAt: string } | null>(null);
    const [attendanceHistoryEmployee, setAttendanceHistoryEmployee] = useState<ManagedEmployee | null>(null);
    const sessionForm = useForm(defaultSessionForm);
    const employeeForm = useForm({ name: '', is_active: true });
    const userForm = useForm(defaultUserForm);
    const deviceTrustForm = useForm({ enabled: deviceTrust.enabled });
    const attendanceDeviceForm = useForm({ enabled: attendanceDeviceRestriction.enabled });
    const deviceForm = useForm({ name: '', is_active: true, allows_attendance: false });
    const codeForm = useForm({ label: '' });
    const scheduleForm = useForm({ start_time: workSchedule.start_time });
    const bonusForm = useForm({
        visit_threshold: bonusProgram.visit_threshold,
        validity_days: bonusProgram.validity_days ?? 30,
        expiry_check_time: bonusProgram.expiry_check_time ?? '20:00',
    });
    const pruneForm = useForm({ confirmation: '' });

    const changeTab = (tab: ManagementTab) => {
        setActiveTab(tab);

        const nextUrl = `${window.location.pathname}#${tab}`;

        if (`${window.location.pathname}${window.location.hash}` !== nextUrl) {
            window.history.replaceState(null, '', nextUrl);
        }
    };

    useEffect(() => {
        if (flash?.generated_device_code) {
            changeTab('devices');
            setGeneratedCode({
                code: flash.generated_device_code,
                expiresAt: flash.generated_device_code_expires_at ?? '',
            });
            setGeneratedCodeOpen(true);
        }
    }, [flash?.generated_device_code, flash?.generated_device_code_expires_at]);

    useEffect(() => {
        const syncTabFromHash = () => {
            setActiveTab(managementTabFromHash(window.location.hash));
        };

        window.addEventListener('hashchange', syncTabFromHash);

        return () => window.removeEventListener('hashchange', syncTabFromHash);
    }, []);

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

    const openAttendanceHistory = (employee: ManagedEmployee) => {
        setAttendanceHistoryEmployee(employee);
    };

    const openCreateUser = () => {
        setEditingUser(null);
        userForm.reset();
        userForm.setData(defaultUserForm);
        setUserDialogOpen(true);
    };

    const openEditUser = (user: ManagedUser) => {
        setEditingUser(user);
        userForm.setData({
            name: user.name,
            email: user.email,
            password: '',
            password_confirmation: '',
            role: user.role,
            is_active: user.is_active,
        });
        setUserDialogOpen(true);
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

    const submitBonusProgram = (event: FormEvent) => {
        event.preventDefault();
        bonusForm.patch(route('management.bonus-program.update'), { preserveScroll: true });
    };

    const submitUser = (event: FormEvent) => {
        event.preventDefault();

        if (editingUser) {
            userForm.patch(route('management.users.update', editingUser.id), {
                onSuccess: () => setUserDialogOpen(false),
            });

            return;
        }

        userForm.post(route('management.users.store'), {
            onSuccess: () => {
                setUserDialogOpen(false);
                userForm.reset();
            },
        });
    };

    const toggleDeviceTrust = (enabled: boolean) => {
        deviceTrustForm.setData('enabled', enabled);
        deviceTrustForm.patch(route('management.device-trust.update'), { preserveScroll: true });
    };

    const toggleAttendanceDeviceRestriction = (enabled: boolean) => {
        attendanceDeviceForm.setData('enabled', enabled);
        attendanceDeviceForm.patch(route('management.attendance-device-restriction.update'), { preserveScroll: true });
    };

    const openCreateCode = () => {
        codeForm.reset();
        codeForm.setData({ label: '' });
        setCodeDialogOpen(true);
    };

    const submitCode = (event: FormEvent) => {
        event.preventDefault();
        codeForm.post(route('management.device-codes.store'), {
            preserveScroll: true,
            onSuccess: () => setCodeDialogOpen(false),
        });
    };

    const openEditDevice = (device: ManagedTrustedDevice) => {
        setEditingDevice(device);
        deviceForm.setData({
            name: device.name,
            is_active: device.is_active,
            allows_attendance: device.allows_attendance,
        });
        setDeviceDialogOpen(true);
    };

    const submitDevice = (event: FormEvent) => {
        event.preventDefault();

        if (!editingDevice) {
            return;
        }

        deviceForm.patch(route('management.trusted-devices.update', editingDevice.id), {
            onSuccess: () => setDeviceDialogOpen(false),
        });
    };

    const revokeDevice = async (device: ManagedTrustedDevice) => {
        const confirmed = await confirm({
            title: 'إلغاء توثيق الجهاز',
            description: `إلغاء توثيق الجهاز «${device.name}»؟`,
            confirmLabel: 'إلغاء التوثيق',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(route('management.trusted-devices.destroy', device.id), {
            preserveScroll: true,
        });
    };

    const deleteEmployee = async (employee: ManagedEmployee) => {
        const confirmed = await confirm({
            title: 'حذف الموظف',
            description: `حذف الموظف «${employee.name}» نهائياً؟\nسيتم حذف ${employee.attendance_records_count} سجل حضور ولا يمكن التراجع.`,
            confirmLabel: 'حذف',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(route('management.employees.destroy', employee.id), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إدارة النظام" />
            <FlashAlert message={flash?.success} />

            <ManagementLayout activeTab={activeTab} onTabChange={changeTab}>
            {activeTab === 'sessions' && (
                <Card className="raad-card-surface">
                    <CardHeader className="raad-card-header">
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
                        <div className="rounded-xl border">
                            <RaadDesktopTable>
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
                            </RaadDesktopTable>
                            <RaadMobileStack>
                                {sessionDurations.map((session) => (
                                    <RaadMobileCard key={session.id}>
                                        <RaadMobileCardHeader
                                            title={<SessionBadge name={session.name} color={session.color} />}
                                            trailing={
                                                <Badge variant={session.is_active ? 'default' : 'secondary'}>
                                                    {session.is_active ? 'مفعّل' : 'معطّل'}
                                                </Badge>
                                            }
                                        />
                                        <RaadMobileBody>
                                            <RaadMobileGrid cols={3}>
                                                <RaadMobileStat label="المدة">
                                                    {formatDuration(session.duration_minutes, session.is_open)}
                                                </RaadMobileStat>
                                                <RaadMobileStat label="الترتيب">{session.sort_order}</RaadMobileStat>
                                                <RaadMobileStat label="الفواتير">{session.invoices_count}</RaadMobileStat>
                                            </RaadMobileGrid>
                                        </RaadMobileBody>
                                        <RaadMobileActions>
                                            <Button type="button" variant="outline" size="sm" onClick={() => openEditSession(session)}>
                                                <Pencil className="size-4" />
                                                تعديل
                                            </Button>
                                        </RaadMobileActions>
                                    </RaadMobileCard>
                                ))}
                            </RaadMobileStack>
                        </div>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'users' && (
                <Card className="raad-card-surface">
                    <CardHeader className="raad-card-header">
                        <div>
                            <CardTitle>إدارة المستخدمين</CardTitle>
                            <CardDescription>
                                إضافة حسابات الدخول وتعيين الصلاحيات (مدير النظام أو موظف استقبال) وتعطيل المستخدمين
                            </CardDescription>
                        </div>
                        <Button type="button" onClick={openCreateUser}>
                            <Plus className="size-4" />
                            إضافة مستخدم
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-xl border">
                            <RaadDesktopTable>
                                <thead className="raad-table-head">
                                    <tr>
                                        <th className="px-4 py-3 text-right font-medium">الاسم</th>
                                        <th className="px-4 py-3 text-right font-medium">البريد</th>
                                        <th className="px-4 py-3 text-right font-medium">الصلاحية</th>
                                        <th className="px-4 py-3 text-right font-medium">الحالة</th>
                                        <th className="px-4 py-3 text-right font-medium">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-t">
                                            <td className="px-4 py-3 font-medium">
                                                {user.name}
                                                {user.id === auth.user.id && (
                                                    <Badge variant="outline" className="ms-2">
                                                        أنت
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3" dir="ltr">
                                                {user.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={user.role === 'admin' ? 'default' : 'secondary'}>{user.role_label}</Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={user.is_active ? 'default' : 'secondary'}>
                                                    {user.is_active ? 'نشط' : 'معطّل'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Button type="button" variant="ghost" size="sm" onClick={() => openEditUser(user)}>
                                                    <Pencil className="size-4" />
                                                    تعديل
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </RaadDesktopTable>
                            <RaadMobileStack>
                                {users.map((user) => (
                                    <RaadMobileCard key={user.id}>
                                        <RaadMobileCardHeader
                                            title={
                                                <span className="inline-flex flex-wrap items-center gap-2">
                                                    {user.name}
                                                    {user.id === auth.user.id && <Badge variant="outline">أنت</Badge>}
                                                </span>
                                            }
                                            trailing={
                                                <Badge variant={user.is_active ? 'default' : 'secondary'}>
                                                    {user.is_active ? 'نشط' : 'معطّل'}
                                                </Badge>
                                            }
                                        />
                                        <RaadMobileBody>
                                            <RaadMobileStat label="البريد" dir="ltr">
                                                {user.email}
                                            </RaadMobileStat>
                                            <RaadMobileChips>
                                                <Badge variant={user.role === 'admin' ? 'default' : 'secondary'}>{user.role_label}</Badge>
                                            </RaadMobileChips>
                                        </RaadMobileBody>
                                        <RaadMobileActions>
                                            <Button type="button" variant="outline" size="sm" onClick={() => openEditUser(user)}>
                                                <Pencil className="size-4" />
                                                تعديل
                                            </Button>
                                        </RaadMobileActions>
                                    </RaadMobileCard>
                                ))}
                            </RaadMobileStack>
                        </div>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'devices' && (
                <Card className="raad-card-surface">
                    <CardHeader className="raad-card-header">
                        <div>
                            <CardTitle>الأجهزة الموثّقة</CardTitle>
                            <CardDescription>
                                اسمح فقط للأجهزة الموثّقة باستخدام النظام عبر رمز لمرة واحدة يُنشئه المدير
                            </CardDescription>
                        </div>
                        <Button type="button" onClick={openCreateCode}>
                            <Plus className="size-4" />
                            إنشاء رمز توثيق
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-8">
                        <ManagementSection
                            title="إعدادات الأمان"
                            description="تحكم في تقييد الدخول وتسجيل الحضور من الأجهزة الموثّقة فقط"
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 p-4">
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <Label htmlFor="device_trust_enabled" className="font-medium">
                                            تقييد الأجهزة
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            عند التفعيل، موظفو الاستقبال يحتاجون جهازاً موثّقاً. المدير يمكنه الدخول من أي جهاز.
                                        </p>
                                        <InputError message={deviceTrustForm.errors.enabled} />
                                    </div>
                                    <Switch
                                        id="device_trust_enabled"
                                        checked={deviceTrustForm.data.enabled}
                                        disabled={deviceTrustForm.processing}
                                        onCheckedChange={toggleDeviceTrust}
                                    />
                                </div>

                                <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 p-4">
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <Label htmlFor="attendance_device_restriction_enabled" className="font-medium">
                                            تقييد تسجيل الحضور
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            عند التفعيل، يُسجَّل الحضور والانصراف فقط من الأجهزة الموثّقة المحددة للحضور.
                                        </p>
                                        <InputError message={attendanceDeviceForm.errors.enabled} />
                                    </div>
                                    <Switch
                                        id="attendance_device_restriction_enabled"
                                        checked={attendanceDeviceForm.data.enabled}
                                        disabled={attendanceDeviceForm.processing}
                                        onCheckedChange={toggleAttendanceDeviceRestriction}
                                    />
                                </div>
                            </div>
                        </ManagementSection>

                        {pendingDeviceCodes.length > 0 && (
                            <ManagementSection title="رموز التوثيق النشطة" description="رموز لمرة واحدة لم تُستخدم بعد أو لم تنتهِ صلاحيتها">
                                <div className="rounded-xl border">
                                <RaadDesktopTable>
                                    <thead className="raad-table-head">
                                        <tr>
                                            <th className="px-4 py-3 text-right font-medium">الوصف</th>
                                            <th className="px-4 py-3 text-right font-medium">ينتهي في</th>
                                            <th className="px-4 py-3 text-right font-medium">أُنشئ بواسطة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendingDeviceCodes.map((code) => (
                                            <tr key={code.id} className="border-t">
                                                <td className="px-4 py-3">{code.label ?? '—'}</td>
                                                <td className="px-4 py-3">{formatDateTime(code.expires_at)}</td>
                                                <td className="px-4 py-3">{code.created_by_name ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </RaadDesktopTable>
                                <RaadMobileStack>
                                    {pendingDeviceCodes.map((code) => (
                                        <RaadMobileCard key={code.id}>
                                            <RaadMobileCardHeader title={code.label ?? '—'} />
                                            <RaadMobileBody>
                                                <RaadMobileGrid>
                                                    <RaadMobileStat label="ينتهي في">{formatDateTime(code.expires_at)}</RaadMobileStat>
                                                    <RaadMobileStat label="أُنشئ بواسطة">{code.created_by_name ?? '—'}</RaadMobileStat>
                                                </RaadMobileGrid>
                                            </RaadMobileBody>
                                        </RaadMobileCard>
                                    ))}
                                </RaadMobileStack>
                            </div>
                            </ManagementSection>
                        )}

                        <ManagementSection title="الأجهزة المسجّلة" description="قائمة الأجهزة التي أكملت التوثيق ويمكن إدارتها">
                        <div className="rounded-xl border">
                            <RaadDesktopTable>
                                <thead className="raad-table-head">
                                    <tr>
                                        <th className="px-4 py-3 text-right font-medium">الجهاز</th>
                                        <th className="px-4 py-3 text-right font-medium">آخر استخدام</th>
                                        <th className="px-4 py-3 text-right font-medium">الحالة</th>
                                        <th className="px-4 py-3 text-right font-medium">الحضور</th>
                                        <th className="px-4 py-3 text-right font-medium">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {trustedDevices.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                                لا توجد أجهزة موثّقة بعد
                                            </td>
                                        </tr>
                                    ) : (
                                        trustedDevices.map((device) => (
                                            <tr key={device.id} className="border-t">
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">{device.name}</span>
                                                        {device.is_currently_active && (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1.5 border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            >
                                                                <span className="size-2 animate-pulse rounded-full bg-emerald-500" />
                                                                نشط حالياً
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {device.user_agent && (
                                                        <div className="text-muted-foreground mt-1 max-w-md truncate text-xs" dir="ltr">
                                                            {device.user_agent}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">{formatDateTime(device.last_used_at)}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={device.is_active ? 'default' : 'secondary'}>
                                                        {device.is_active ? 'موثّق' : 'معطّل'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={device.allows_attendance ? 'default' : 'secondary'}>
                                                        {device.allows_attendance ? 'مسموح' : '—'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Button type="button" variant="ghost" size="sm" onClick={() => openEditDevice(device)}>
                                                            <Pencil className="size-4" />
                                                            تعديل
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive"
                                                            onClick={() => revokeDevice(device)}
                                                        >
                                                            <Trash2 className="size-4" />
                                                            إلغاء
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </RaadDesktopTable>
                            {trustedDevices.length === 0 ? (
                                <RaadTableEmpty className="md:hidden">لا توجد أجهزة موثّقة بعد</RaadTableEmpty>
                            ) : (
                                <RaadMobileStack>
                                    {trustedDevices.map((device) => (
                                        <RaadMobileCard key={device.id}>
                                            <RaadMobileCardHeader
                                                title={device.name}
                                                trailing={
                                                    device.is_currently_active ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <span className="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                                                            نشط
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant={device.is_active ? 'default' : 'secondary'}>
                                                            {device.is_active ? 'موثّق' : 'معطّل'}
                                                        </Badge>
                                                    )
                                                }
                                            />
                                            <RaadMobileBody>
                                                <RaadMobileStat label="آخر استخدام">{formatDateTime(device.last_used_at)}</RaadMobileStat>
                                                <RaadMobileChips>
                                                    <Badge variant={device.allows_attendance ? 'default' : 'secondary'}>
                                                        حضور: {device.allows_attendance ? 'مسموح' : '—'}
                                                    </Badge>
                                                </RaadMobileChips>
                                                {device.user_agent && (
                                                    <RaadMobileStat label="المتصفح" dir="ltr">
                                                        <span className="break-all text-xs">{device.user_agent}</span>
                                                    </RaadMobileStat>
                                                )}
                                            </RaadMobileBody>
                                            <RaadMobileActions>
                                                <Button type="button" variant="outline" size="sm" onClick={() => openEditDevice(device)}>
                                                    <Pencil className="size-4" />
                                                    تعديل
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="text-destructive"
                                                    onClick={() => revokeDevice(device)}
                                                >
                                                    <Trash2 className="size-4" />
                                                    إلغاء
                                                </Button>
                                            </RaadMobileActions>
                                        </RaadMobileCard>
                                    ))}
                                </RaadMobileStack>
                            )}
                        </div>
                        </ManagementSection>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'maintenance' && (
                <Card className="raad-card-surface border-rose-500/20">
                    <CardHeader className="raad-card-header">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Archive className="size-5 text-rose-600" />
                                تنظيف الفواتير السابقة
                            </CardTitle>
                            <CardDescription>
                                حذف الفواتير المكتملة القديمة مع الإبقاء على فواتير آخر 4 زيارات لكل عميل
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <ManagementSection title="الإحصائيات الحالية">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                    <p className="text-muted-foreground mb-1 text-xs">فواتير مكتملة حالياً</p>
                                    <p className="text-2xl font-bold">{invoiceCleanup.completed_invoices_count}</p>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                    <p className="text-muted-foreground mb-1 text-xs">فواتير ملغاة (تُحذف أيضاً)</p>
                                    <p className="text-2xl font-bold">{invoiceCleanup.cancelled_invoices_count}</p>
                                </div>
                            </div>
                        </ManagementSection>

                        <Separator />

                        <ManagementSection
                            title="تنفيذ التنظيف"
                            description="لا تُحذف الفواتير النشطة، ولا يتغيّر عدد الزيارات المخزَّن عند العميل"
                        >
                        <form
                            onSubmit={(event: FormEvent) => {
                                event.preventDefault();
                                pruneForm.post(route('management.prune-completed-invoices'), {
                                    preserveScroll: true,
                                    onSuccess: () => pruneForm.reset(),
                                });
                            }}
                            className="space-y-4 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4"
                        >
                            <p className="text-sm leading-relaxed">
                                لكل عميل يُحدَّد آخر <strong>4 أيام زيارة</strong> (حسب الفواتير المحتسبة) ويُبقى عليها كل الفواتير
                                المكتملة في تلك الأيام. تُحذف باقي الفواتير المكتملة والملغاة نهائياً من قاعدة البيانات.
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="prune_confirmation">اكتب «حذف الفواتير» للتأكيد</Label>
                                <Input
                                    id="prune_confirmation"
                                    value={pruneForm.data.confirmation}
                                    onChange={(event) => pruneForm.setData('confirmation', event.target.value)}
                                    placeholder="حذف الفواتير"
                                    autoComplete="off"
                                />
                                <InputError message={pruneForm.errors.confirmation} />
                            </div>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={pruneForm.processing || pruneForm.data.confirmation !== 'حذف الفواتير'}
                            >
                                <Trash2 className="size-4" />
                                تنفيذ التنظيف
                            </Button>
                        </form>
                        </ManagementSection>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'bonus' && (
                <Card className="raad-card-surface">
                    <CardHeader className="raad-card-header">
                        <div>
                            <CardTitle>برنامج المكافآت</CardTitle>
                            <CardDescription>
                                عند وصول العميل لعدد الزيارات المحدد تُميَّز الفاتورة في الجدول وتصبح مؤهلة لتفعيل مكافأة مجانية
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitBonusProgram} className="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-4">
                            <div className="grid gap-4 md:grid-cols-2">
                            <div className="min-w-0 space-y-2">
                                <Label htmlFor="visit_threshold" className="flex items-center gap-1.5">
                                    <Gift className="text-muted-foreground size-3.5" />
                                    عدد الزيارات قبل المكافأة
                                </Label>
                                <Input
                                    id="visit_threshold"
                                    type="number"
                                    min={1}
                                    max={50}
                                    value={bonusForm.data.visit_threshold}
                                    onChange={(event) => bonusForm.setData('visit_threshold', Number(event.target.value))}
                                    required
                                />
                                <InputError message={bonusForm.errors.visit_threshold} />
                                <p className="text-muted-foreground text-xs">
                                    القيمة الحالية: {bonusProgram.visit_threshold} — الزيارة التالية ({bonusProgram.visit_threshold + 1}) تكون
                                    مكافأة
                                </p>
                            </div>
                            <div className="min-w-0 space-y-2">
                                <Label htmlFor="validity_days" className="flex items-center gap-1.5">
                                    <CalendarDays className="text-muted-foreground size-3.5" />
                                    مدة صلاحية المكافأة (يوم)
                                </Label>
                                <Input
                                    id="validity_days"
                                    type="number"
                                    min={1}
                                    max={365}
                                    value={bonusForm.data.validity_days}
                                    onChange={(event) => bonusForm.setData('validity_days', Number(event.target.value))}
                                    required
                                />
                                <InputError message={bonusForm.errors.validity_days} />
                                <p className="text-muted-foreground text-xs">
                                    القيمة الحالية: {bonusProgram.validity_days ?? 30} يوم — بعد انتهائها تُسجَّل كمكافأة منتهية في
                                    الفواتير السابقة
                                </p>
                            </div>
                            <div className="min-w-0 space-y-2 md:col-span-2">
                                <Label htmlFor="expiry_check_time" className="flex items-center gap-1.5">
                                    <Clock className="text-muted-foreground size-3.5" />
                                    ساعة التحقق من انتهاء المكافآت
                                </Label>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <RaadTimeInput
                                        id="expiry_check_time"
                                        aria-label="ساعة التحقق من انتهاء المكافآت"
                                        value={bonusForm.data.expiry_check_time}
                                        onChange={(expiry_check_time) => bonusForm.setData('expiry_check_time', expiry_check_time)}
                                        className="w-full max-w-[9rem] bg-background"
                                        required
                                    />
                                    <p className="text-muted-foreground text-xs sm:flex-1">
                                        يُنفَّذ التحقق يومياً عند{' '}
                                        <span dir="ltr">
                                            {formatTimeString12(bonusProgram.expiry_check_time ?? '20:00')}
                                        </span>
                                        . تبقى المكافأة قابلة للاستخدام بعد انتهاء صلاحيتها حتى هذا الموعد، ثم تُسجَّل
                                        كمكافأة منتهية إن لم تُفعَّل
                                    </p>
                                </div>
                                <InputError message={bonusForm.errors.expiry_check_time} />
                            </div>
                            </div>
                            <div className="flex justify-end border-t border-border/60 pt-4">
                            <Button type="submit" disabled={bonusForm.processing}>
                                حفظ الإعدادات
                            </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'employees' && (
                <Card className="raad-card-surface">
                    <CardHeader className="raad-card-header">
                        <div>
                            <CardTitle>إدارة الموظفين</CardTitle>
                            <CardDescription>إضافة وتعديل الموظفين ومتابعة سجلات الحضور</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-8">
                        <form
                            onSubmit={submitSchedule}
                            className="rounded-xl border border-border/60 bg-muted/20 p-4"
                        >
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:gap-4">
                                <Label htmlFor="start_time" className="flex shrink-0 items-center gap-1.5 font-medium">
                                    <Clock className="text-muted-foreground size-4" />
                                    وقت بدء الدوام
                                </Label>
                                <RaadTimeInput
                                    id="start_time"
                                    aria-label="وقت بدء الدوام"
                                    value={scheduleForm.data.start_time}
                                    onChange={(start_time) => scheduleForm.setData('start_time', start_time)}
                                    className="w-full max-w-[9rem] bg-background"
                                    required
                                />
                                <p className="text-muted-foreground text-xs md:flex-1">
                                    الوقت المحفوظ حالياً: <span dir="ltr">{formatTimeString12(workSchedule.start_time)}</span>
                                </p>
                                <Button type="submit" className="min-h-10 shrink-0 md:ms-auto" disabled={scheduleForm.processing}>
                                    حفظ وقت الدوام
                                </Button>
                            </div>
                            <InputError message={scheduleForm.errors.start_time} className="mt-2" />
                        </form>

                        <Separator />

                        <ManagementSection
                            title="قائمة الموظفين"
                            description="إدارة حالة الموظفين وسجل الحضور لكل موظف"
                            action={
                                <Button type="button" onClick={openCreateEmployee}>
                                    <Plus className="size-4" />
                                    إضافة موظف
                                </Button>
                            }
                        >
                        <div className="rounded-xl border">
                            <RaadDesktopTable>
                                <thead className="raad-table-head">
                                    <tr>
                                        <th className="px-4 py-3 text-right font-medium">الاسم</th>
                                        <th className="px-4 py-3 text-right font-medium">الحالة</th>
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
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-primary"
                                                        disabled={employee.attendance_records_count === 0}
                                                        onClick={() => openAttendanceHistory(employee)}
                                                    >
                                                        <History className="size-4" />
                                                        سجل الحضور
                                                    </Button>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => openEditEmployee(employee)}>
                                                        <Pencil className="size-4" />
                                                        تعديل
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => deleteEmployee(employee)}
                                                    >
                                                        <Trash2 className="size-4" />
                                                        حذف
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </RaadDesktopTable>
                            <RaadMobileStack>
                                {employees.map((employee) => (
                                    <RaadMobileCard key={employee.id}>
                                        <RaadMobileCardHeader
                                            title={employee.name}
                                            trailing={
                                                <Badge variant={employee.is_active ? 'default' : 'secondary'}>
                                                    {employee.is_active ? 'يعمل' : 'معطّل'}
                                                </Badge>
                                            }
                                        />
                                        <RaadMobileActions>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="text-primary"
                                                disabled={employee.attendance_records_count === 0}
                                                onClick={() => openAttendanceHistory(employee)}
                                            >
                                                <History className="size-4" />
                                                سجل الحضور
                                            </Button>
                                            <Button type="button" variant="outline" size="sm" onClick={() => openEditEmployee(employee)}>
                                                <Pencil className="size-4" />
                                                تعديل
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="text-destructive"
                                                onClick={() => deleteEmployee(employee)}
                                            >
                                                <Trash2 className="size-4" />
                                                حذف
                                            </Button>
                                        </RaadMobileActions>
                                    </RaadMobileCard>
                                ))}
                            </RaadMobileStack>
                        </div>
                        </ManagementSection>
                    </CardContent>
                </Card>
            )}

            </ManagementLayout>

            <Dialog open={sessionDialogOpen} onOpenChange={setSessionDialogOpen}>
                <DialogContent className="md:max-w-md">
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

                        <div className="flex items-center justify-between gap-4 rounded-xl border p-3">
                            <Label htmlFor="is_open" className="flex-1">
                                جلسة مفتوحة (بدون مدة محددة)
                            </Label>
                            <Switch
                                id="is_open"
                                checked={sessionForm.data.is_open}
                                onCheckedChange={(checked) => {
                                    sessionForm.setData({
                                        ...sessionForm.data,
                                        is_open: checked,
                                        duration_minutes: checked ? null : (sessionForm.data.duration_minutes ?? 60),
                                    });
                                }}
                            />
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
                            <div className="flex items-center justify-between gap-4 rounded-xl border p-3">
                                <Label htmlFor="is_active" className="flex-1">
                                    مفعّل في النظام
                                </Label>
                                <Switch
                                    id="is_active"
                                    checked={sessionForm.data.is_active}
                                    onCheckedChange={(checked) => sessionForm.setData('is_active', checked)}
                                />
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
                <DialogContent className="md:max-w-md">
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
                                <div className="flex items-center justify-between gap-4">
                                    <Label htmlFor="employee_active" className="flex-1">
                                        يعمل حالياً ويظهر في صفحة الحضور
                                    </Label>
                                    <Switch
                                        id="employee_active"
                                        checked={employeeForm.data.is_active}
                                        onCheckedChange={(checked) => employeeForm.setData('is_active', checked)}
                                    />
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

            <Dialog open={userDialogOpen} onOpenChange={setUserDialogOpen}>
                <DialogContent className="md:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingUser ? 'تعديل المستخدم' : 'إضافة مستخدم'}</DialogTitle>
                        <DialogDescription>
                            {editingUser
                                ? 'تعطيل المستخدم يمنعه من تسجيل الدخول دون حذف سجله'
                                : 'سيستخدم المستخدم البريد وكلمة المرور لتسجيل الدخول إلى النظام'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitUser} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="user_name">الاسم</Label>
                            <Input
                                id="user_name"
                                value={userForm.data.name}
                                onChange={(event) => userForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={userForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user_email">البريد الإلكتروني</Label>
                            <Input
                                id="user_email"
                                type="email"
                                dir="ltr"
                                value={userForm.data.email}
                                onChange={(event) => userForm.setData('email', event.target.value)}
                                required
                            />
                            <InputError message={userForm.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user_role">الصلاحية</Label>
                            <Select
                                modal={false}
                                value={userForm.data.role}
                                onValueChange={(value: ManagedUser['role']) => userForm.setData('role', value)}
                                disabled={editingUser?.id === auth.user.id}
                            >
                                <SelectTrigger id="user_role">
                                    <SelectValue placeholder="اختر الصلاحية" />
                                </SelectTrigger>
                                <SelectContent position="popper" className="z-[110]">
                                    <SelectItem value="admin">مدير النظام</SelectItem>
                                    <SelectItem value="reception">موظف استقبال</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={userForm.errors.role} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user_password">{editingUser ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'}</Label>
                            <Input
                                id="user_password"
                                type="password"
                                value={userForm.data.password}
                                onChange={(event) => userForm.setData('password', event.target.value)}
                                required={!editingUser}
                                autoComplete={editingUser ? 'new-password' : 'new-password'}
                            />
                            <InputError message={userForm.errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user_password_confirmation">تأكيد كلمة المرور</Label>
                            <Input
                                id="user_password_confirmation"
                                type="password"
                                value={userForm.data.password_confirmation}
                                onChange={(event) => userForm.setData('password_confirmation', event.target.value)}
                                required={!editingUser && userForm.data.password.length > 0}
                                autoComplete="new-password"
                            />
                            <InputError message={userForm.errors.password_confirmation} />
                        </div>

                        {editingUser && (
                            <div className="space-y-2 rounded-xl border p-3">
                                <div className="flex items-center justify-between gap-4">
                                    <Label htmlFor="user_active" className="flex-1">
                                        الحساب نشط ويمكنه تسجيل الدخول
                                    </Label>
                                    <Switch
                                        id="user_active"
                                        checked={userForm.data.is_active}
                                        disabled={editingUser.id === auth.user.id}
                                        onCheckedChange={(checked) => userForm.setData('is_active', checked)}
                                    />
                                </div>
                                {editingUser.id === auth.user.id && (
                                    <p className="text-muted-foreground text-xs">لا يمكنك تعطيل حسابك الحالي أو تغيير صلاحيتك.</p>
                                )}
                                {!userForm.data.is_active && editingUser.id !== auth.user.id && (
                                    <p className="text-muted-foreground text-xs">لن يتمكن المستخدم من تسجيل الدخول حتى إعادة تفعيله.</p>
                                )}
                                <InputError message={userForm.errors.is_active} />
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setUserDialogOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={userForm.processing}>
                                {editingUser ? 'حفظ التعديلات' : 'إضافة'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={codeDialogOpen} onOpenChange={setCodeDialogOpen}>
                <DialogContent className="md:max-w-md">
                    <DialogHeader>
                        <DialogTitle>إنشاء رمز توثيق</DialogTitle>
                        <DialogDescription>أدخل الرمز على الجهاز المطلوب خلال 15 دقيقة — يُستخدم مرة واحدة فقط</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCode} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="code_label">وصف الجهاز (اختياري)</Label>
                            <Input
                                id="code_label"
                                value={codeForm.data.label}
                                onChange={(event) => codeForm.setData('label', event.target.value)}
                                placeholder="مثال: تابلت الاستقبال"
                            />
                            <InputError message={codeForm.errors.label} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCodeDialogOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={codeForm.processing}>
                                إنشاء الرمز
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={generatedCodeOpen} onOpenChange={setGeneratedCodeOpen}>
                <DialogContent className="md:max-w-md">
                    <DialogHeader>
                        <DialogTitle>رمز التوثيق</DialogTitle>
                        <DialogDescription>انسخ الرمز وأدخله على الجهاز من صفحة «توثيق الجهاز»</DialogDescription>
                    </DialogHeader>
                    {generatedCode && (
                        <div className="space-y-4">
                            <div className="rounded-xl border bg-muted/30 px-4 py-6 text-center">
                                <p className="font-mono text-3xl font-bold tracking-[0.3em]" dir="ltr">
                                    {generatedCode.code}
                                </p>
                            </div>
                            <p className="text-muted-foreground text-center text-sm">
                                ينتهي في: {formatDateTime(generatedCode.expiresAt)}
                            </p>
                            <Button type="button" className="w-full" onClick={() => setGeneratedCodeOpen(false)}>
                                تم
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={deviceDialogOpen} onOpenChange={setDeviceDialogOpen}>
                <DialogContent className="md:max-w-md">
                    <DialogHeader>
                        <DialogTitle>تعديل الجهاز</DialogTitle>
                        <DialogDescription>تعطيل الجهاز يمنع استخدام النظام من هذا المتصفح</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitDevice} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="device_name">اسم الجهاز</Label>
                            <Input
                                id="device_name"
                                value={deviceForm.data.name}
                                onChange={(event) => deviceForm.setData('name', event.target.value)}
                                required
                            />
                            <InputError message={deviceForm.errors.name} />
                        </div>
                        <div className="space-y-3 rounded-xl border p-3">
                            <div className="flex items-center justify-between gap-4">
                                <Label htmlFor="device_active" className="flex-1">
                                    الجهاز موثّق ويمكنه استخدام النظام
                                </Label>
                                <Switch
                                    id="device_active"
                                    checked={deviceForm.data.is_active}
                                    onCheckedChange={(checked) => deviceForm.setData('is_active', checked)}
                                />
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <Label htmlFor="device_allows_attendance" className="flex-1">
                                    يسمح بتسجيل الحضور والانصراف
                                </Label>
                                <Switch
                                    id="device_allows_attendance"
                                    checked={deviceForm.data.allows_attendance}
                                    onCheckedChange={(checked) => deviceForm.setData('allows_attendance', checked)}
                                />
                            </div>
                        </div>
                        <InputError message={deviceForm.errors.is_active} />
                        <InputError message={deviceForm.errors.allows_attendance} />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDeviceDialogOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={deviceForm.processing}>
                                حفظ
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <EmployeeAttendanceDialog
                employeeId={attendanceHistoryEmployee?.id ?? null}
                employeeName={attendanceHistoryEmployee?.name}
                open={attendanceHistoryEmployee !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAttendanceHistoryEmployee(null);
                    }
                }}
            />
        </AppLayout>
    );
}
