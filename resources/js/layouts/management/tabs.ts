import { Archive, Gift, MonitorSmartphone, Settings2, ShieldCheck, Timer, Users, type LucideIcon } from 'lucide-react';

export type ManagementTab = 'sessions' | 'employees' | 'users' | 'devices' | 'bonus' | 'maintenance';

export interface ManagementTabItem {
    id: ManagementTab;
    label: string;
    icon: LucideIcon;
    iconClass: string;
}

export const managementTabs: ManagementTabItem[] = [
    {
        id: 'sessions',
        label: 'أنواع الجلسات',
        icon: Timer,
        iconClass: 'text-violet-600 dark:text-violet-400',
    },
    {
        id: 'employees',
        label: 'إدارة الموظفين',
        icon: Users,
        iconClass: 'text-sky-600 dark:text-sky-400',
    },
    {
        id: 'users',
        label: 'المستخدمون',
        icon: ShieldCheck,
        iconClass: 'text-amber-600 dark:text-amber-400',
    },
    {
        id: 'devices',
        label: 'الأجهزة الموثّقة',
        icon: MonitorSmartphone,
        iconClass: 'text-indigo-600 dark:text-indigo-400',
    },
    {
        id: 'bonus',
        label: 'برنامج المكافآت',
        icon: Gift,
        iconClass: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        id: 'maintenance',
        label: 'تنظيف البيانات',
        icon: Archive,
        iconClass: 'text-rose-600 dark:text-rose-400',
    },
];

export const defaultManagementTab: ManagementTab = 'sessions';

export function isManagementTab(value: string): value is ManagementTab {
    return managementTabs.some((tab) => tab.id === value);
}

export function managementTabFromHash(hash: string): ManagementTab {
    const normalized = hash.replace(/^#/, '');

    return isManagementTab(normalized) ? normalized : defaultManagementTab;
}

export const managementPageIcon = Settings2;
