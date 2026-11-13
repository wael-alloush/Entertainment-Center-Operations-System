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
    nextInvoiceNumber: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'الفواتير النشطة', href: '/invoices/active' }];

export default function ActiveInvoices({ invoices, sessionDurations, summary, filters, nextInvoiceNumber }: ActiveInvoicesProps) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الفواتير النشطة" />
            <FlashAlert message={flash?.error} variant="error" />
            <FlashAlert message={flash?.success} />
            <InvoicesTable
                invoices={invoices}
                sessionDurations={sessionDurations}
                summary={summary}
                filters={filters}
                nextInvoiceNumber={nextInvoiceNumber}
                variant="active"
                routeName="invoices.active"
            />
        </AppLayout>
    );
}
