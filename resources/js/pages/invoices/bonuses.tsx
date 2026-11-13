import { FlashAlert } from '@/components/raad/flash-alert';
import { PendingBonusesTable } from '@/components/raad/pending-bonuses-table';
import AppLayout from '@/layouts/app-layout';
import { BonusProgramSettings, InvoiceFilters, PaginatedPendingBonuses } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface PendingBonusesPageProps {
    bonuses: PaginatedPendingBonuses;
    bonusProgram: Pick<BonusProgramSettings, 'validity_days'>;
    filters: InvoiceFilters;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'المكافآت', href: '/invoices/bonuses' }];

export default function PendingBonusesPage({ bonuses, bonusProgram, filters }: PendingBonusesPageProps) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="المكافآت غير المفعلة" />
            <FlashAlert message={flash?.error} variant="error" />
            <FlashAlert message={flash?.success} />
            <PendingBonusesTable bonuses={bonuses} filters={filters} validityDays={bonusProgram.validity_days ?? 30} />
        </AppLayout>
    );
}
