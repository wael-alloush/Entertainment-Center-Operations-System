import { FlashAlert } from '@/components/raad/flash-alert';
import { InvoicesTable } from '@/components/raad/invoices-table';
import AppLayout from '@/layouts/app-layout';
import { InvoiceFilters, InvoiceSummary, PaginatedInvoices, SessionDuration } from '@/types/domain';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface ActiveInvoicesProps {
    invoices: PaginatedInvoices;
    sessionDurations: SessionDuration[];
    summary: InvoiceSummary;
    filters: InvoiceFilters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'الفواتير النشطة', href: '/invoices/active' },
];

export default function ActiveInvoices({ invoices, sessionDurations, summary, filters }: ActiveInvoicesProps) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الفواتير النشطة" />
            <FlashAlert message={flash?.success} />
            <InvoicesTable
                invoices={invoices}
                sessionDurations={sessionDurations}
                summary={summary}
                filters={filters}
                variant="active"
                routeName="invoices.active"
            />
        </AppLayout>
    );
}
