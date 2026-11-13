import { Invoice } from '@/types/domain';

export type EditableInvoiceField = 'invoice_number' | 'phone_number' | 'children_count';

type InvoicePermissionContext = Pick<Invoice, 'status' | 'is_credit' | 'is_bonus'> & {
    is_expired_bonus?: boolean;
};

export function canUpdateInvoice(invoice: InvoicePermissionContext | null | undefined): boolean {
    if (!invoice || invoice.is_expired_bonus) {
        return false;
    }

    return invoice.status !== 'cancelled';
}

export function canUpdateInvoiceField(
    invoice: InvoicePermissionContext | null | undefined,
    field: EditableInvoiceField,
): boolean {
    if (!canUpdateInvoice(invoice) || !invoice) {
        return false;
    }

    if (field === 'invoice_number') {
        return !invoice.is_credit && !invoice.is_bonus;
    }

    return true;
}
