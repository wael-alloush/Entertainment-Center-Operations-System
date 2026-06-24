import { FlashAlert } from '@/components/raad/flash-alert';
import { InvoicesTable } from '@/components/raad/invoices-table';
import AppLayout from '@/layouts/app-layout';
import { InvoiceFilters, InvoiceSummary, PaginatedInvoices, SessionDuration } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface CompletedInvoicesProps {
    invoices: PaginatedInvoices;
    sessionDurations: SessionDuration[];
    summary: InvoiceSummary;
    filters: InvoiceFilters;
    canFilterCompletedByDate: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'الفواتير السابقة', href: '/invoices/completed' }];

export default function CompletedInvoices({
    invoices,
    sessionDurations,
    summary,
    filters,
    canFilterCompletedByDate,
}: CompletedInvoicesProps) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الفواتير السابقة" />
            <FlashAlert message={flash?.success} />
            <InvoicesTable
                invoices={invoices}
                sessionDurations={sessionDurations}
                summary={summary}
                filters={filters}
                variant="completed"
                routeName="invoices.completed"
                canFilterCompletedByDate={canFilterCompletedByDate}
            />
        </AppLayout>
    );
}
