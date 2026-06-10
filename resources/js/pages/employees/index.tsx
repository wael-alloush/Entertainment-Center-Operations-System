import { EmployeeCard } from '@/components/raad/employee-card';
import { FlashAlert } from '@/components/raad/flash-alert';
import AppLayout from '@/layouts/app-layout';
import { EmployeeAttendance } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';

interface EmployeesPageProps {
    employees: EmployeeAttendance[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'الموظفين', href: '/employees' },
];

export default function EmployeesPage({ employees }: EmployeesPageProps) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الموظفين" />
            <FlashAlert message={flash?.error} variant="error" />
            <FlashAlert message={flash?.success} />
            <div className="space-y-2">
                <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <span className="raad-icon-chip bg-sky-500/12 text-sky-600 dark:text-sky-400">
                        <Users className="size-5" strokeWidth={2.25} />
                    </span>
                    حضور الموظفين
                </h1>
                <p className="text-muted-foreground text-sm">تسجيل حضور وانصراف فريق العمل اليوم</p>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                {employees.map((employee) => (
                    <EmployeeCard key={employee.id} employee={employee} />
                ))}
            </div>
        </AppLayout>
    );
}
