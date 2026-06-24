import { FlashAlert } from '@/components/raad/flash-alert';
import { PendingCreditsTable } from '@/components/raad/pending-credits-table';
import AppLayout from '@/layouts/app-layout';
import { InvoiceFilters, PaginatedPendingCredits, SessionDuration } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface PendingCreditsPageProps {
    credits: PaginatedPendingCredits;
    sessionDurations: SessionDuration[];
    filters: InvoiceFilters;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'الرصيد', href: '/invoices/credits' }];

export default function PendingCreditsPage({ credits, sessionDurations, filters }: PendingCreditsPageProps) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الرصيد" />
            <FlashAlert message={flash?.success} />
            <PendingCreditsTable credits={credits} filters={filters} sessionDurations={sessionDurations} />
        </AppLayout>
    );
}
