import { useConfirm } from '@/components/raad/confirm-dialog';
import { PreviousVisitsPanel } from '@/components/raad/previous-visits-panel';
import { SessionCard } from '@/components/raad/session-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NumericInput } from '@/components/ui/numeric-input';
import { PhoneInput } from '@/components/ui/phone-input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useLiveRemainingMinute } from '@/hooks/use-live-remaining-minutes';
import { formatRemainingMinutes, remainingTimeBadgeClass, remainingTimeTextClass } from '@/lib/format-time';
import { flushPrefetchedListPages } from '@/lib/inertia-list-pages';
import { canUpdateInvoiceField } from '@/lib/invoice-permissions';
import { getCsrfToken, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import { InvoiceDetail, PreviousVisitInvoice, PreviousVisitsPayload, SessionDuration } from '@/types/domain';
import { router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Clock,
    Gift,
    History,
    LoaderCircle,
    LogOut,
    MessageCircle,
    Pencil,
    Phone,
    Receipt,
    RotateCcw,
    Star,
    Timer,
    Trash2,
    Users,
    Wallet,
    X,
    type LucideIcon,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState, type ReactNode } from 'react';

type DialogView = 'detail' | 'visits' | 'bonus' | 'extend';
type EditableFieldKey = 'invoice_number' | 'phone_number' | 'children_count';

interface ValidationError extends Error {
    errors?: Record<string, string[]>;
    status?: number;
}

interface InvoiceDetailDialogProps {
    invoiceId: number | null;
    expiredBonusId?: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isAdmin?: boolean;
    showReceptionActions?: boolean;
    showReactivate?: boolean;
    showDeleteCompleted?: boolean;
    sessionDurations?: SessionDuration[];
}

function splitDateTime(value: string | null | undefined): { date: string; time: string } {
    if (!value) {
        return { date: '—', time: '—' };
    }

    const [date, ...timeParts] = value.split(' ');

    return { date, time: timeParts.join(' ') || '—' };
}

function splitEndDateTime(invoice: InvoiceDetail | null): { date: string; time: string } {
    const value =
        invoice?.status === 'completed' && invoice.exited_at_full
            ? invoice.exited_at_full
            : (invoice?.expected_exit_at_full ?? invoice?.exited_at_full);

    if (value) {
        return splitDateTime(value);
    }

    if (invoice?.status === 'active') {
        return { date: 'مفتوح', time: 'مفتوح' };
    }

    return { date: '—', time: '—' };
}

function sessionDurationLabel(invoice: InvoiceDetail): string {
    return invoice.session_duration.name;
}

function InfoCard({
    label,
    icon: Icon,
    children,
    className,
}: {
    label: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-1.5 rounded-lg border border-border/60 bg-muted/20 p-2.5 md:space-y-2 md:p-3', className)}>
            <div className="flex items-center gap-1.5">
                {Icon && <Icon className="text-muted-foreground size-3.5" strokeWidth={2.25} />}
                <p className="text-muted-foreground text-xs font-medium">{label}</p>
            </div>
            {children}
        </div>
    );
}

function DetailLoadingSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-2 md:gap-3">
            {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-20 w-full rounded-lg md:h-24" />
            ))}
        </div>
    );
}

function SessionBadge({ name, color }: { name: string; color: string }) {
    return (
        <Badge
            variant="outline"
            className="border-0 px-1.5 py-0 text-xs font-medium text-white shadow-sm"
            style={{ backgroundColor: color, boxShadow: `${color}33 0 2px 8px` }}
        >
            {name}
        </Badge>
    );
}

function EditableInvoiceField({
    label,
    field,
    displayValue,
    editingField,
    editValue,
    fieldError,
    saving,
    valueClassName,
    inputDir,
    inputType = 'text',
    onStartEdit,
    onCancelEdit,
    onEditValueChange,
    onSave,
    icon,
    canEdit = true,
}: {
    label: string;
    field: EditableFieldKey;
    icon?: LucideIcon;
    displayValue: ReactNode;
    editingField: EditableFieldKey | null;
    editValue: string;
    fieldError: string | null;
    saving: boolean;
    valueClassName?: string;
    inputDir?: 'ltr' | 'rtl';
    inputType?: string;
    onStartEdit: (field: EditableFieldKey) => void;
    onCancelEdit: () => void;
    onEditValueChange: (value: string) => void;
    onSave: () => void;
    canEdit?: boolean;
}) {
    const isEditing = editingField === field;

    return (
        <InfoCard label={label} icon={icon}>
            {isEditing ? (
                <div className="space-y-2">
                    {inputType === 'number' ? (
                        <NumericInput
                            maxLength={2}
                            value={editValue}
                            onChange={(e) => onEditValueChange(e.target.value)}
                            dir={inputDir}
                            className="bg-background"
                            autoFocus
                        />
                    ) : (
                        <Input
                            type={inputType}
                            value={editValue}
                            onChange={(e) => onEditValueChange(e.target.value)}
                            dir={inputDir}
                            className="bg-background"
                            autoFocus
                        />
                    )}
                    {fieldError && <p className="text-destructive text-xs">{fieldError}</p>}
                    <div className="flex gap-2">
                        <Button type="button" size="sm" disabled={saving} onClick={onSave}>
                            {saving ? <LoaderCircle className="size-3.5 animate-spin" /> : 'حفظ'}
                        </Button>
                        <Button type="button" size="sm" variant="ghost" disabled={saving} onClick={onCancelEdit}>
                            إلغاء
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="group flex items-center justify-between gap-2">
                    <div className={valueClassName}>{displayValue}</div>
                    {canEdit && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="تعديل"
                            className="text-muted-foreground size-8 shrink-0 opacity-100 md:opacity-70 md:group-hover:opacity-100"
                            onClick={() => onStartEdit(field)}
                        >
                            <Pencil className="size-3.5" />
                        </Button>
                    )}
                </div>
            )}
        </InfoCard>
    );
}

function DateTimeCard({ label, dateTime }: { label: string; dateTime: { date: string; time: string } }) {
    return (
        <InfoCard label={label}>
            <div className="space-y-1.5 md:space-y-2">
                <div className="flex items-center justify-between gap-2 rounded-md border border-border/50 bg-background px-2.5 py-2 md:px-3 md:py-2.5" dir="ltr">
                    <span className="text-sm font-semibold md:text-base">{dateTime.date}</span>
                    <CalendarDays className="text-primary size-3.5 shrink-0 md:size-4" />
                </div>
                <div className="flex items-center justify-between gap-2 rounded-md border border-border/50 bg-background px-2.5 py-2 md:px-3 md:py-2.5" dir="ltr">
                    <span className="text-sm font-semibold md:text-base">{dateTime.time}</span>
                    <Clock className="size-3.5 shrink-0 text-orange-500 md:size-4" />
                </div>
            </div>
        </InfoCard>
    );
}

export function InvoiceDetailDialog({
    invoiceId,
    expiredBonusId = null,
    open,
    onOpenChange,
    isAdmin = false,
    showReceptionActions = false,
    showReactivate = false,
    showDeleteCompleted = false,
    sessionDurations = [],
}: InvoiceDetailDialogProps) {
    const confirm = useConfirm();
    const [view, setView] = useState<DialogView>('detail');
    const [loading, setLoading] = useState(false);
    const [invoice, setInvoice] = useState<InvoiceDetail | null>(null);
    const [visitsPayload, setVisitsPayload] = useState<PreviousVisitsPayload | null>(null);
    const [visitsLoading, setVisitsLoading] = useState(false);
    const [visitsPageLoading, setVisitsPageLoading] = useState(false);
    const [bonusChildrenCount, setBonusChildrenCount] = useState<number | ''>('');
    const [bonusSessionDurationId, setBonusSessionDurationId] = useState<number | null>(null);
    const [bonusSubmitting, setBonusSubmitting] = useState(false);
    const [bonusError, setBonusError] = useState<string | null>(null);
    const [extendDurationId, setExtendDurationId] = useState<number | null>(null);
    const [extendSubmitting, setExtendSubmitting] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [editingField, setEditingField] = useState<EditableFieldKey | null>(null);
    const [editValue, setEditValue] = useState('');
    const [fieldError, setFieldError] = useState<string | null>(null);
    const [fieldSaving, setFieldSaving] = useState(false);
    const [activeInvoiceId, setActiveInvoiceId] = useState<number | null>(null);
    const [activeExpiredBonusId, setActiveExpiredBonusId] = useState<number | null>(null);

    const displayInvoice = useLiveRemainingMinute(
        invoice,
        open && invoice !== null && invoice.status === 'active' && !invoice.is_expired_bonus,
    );

    const loadInvoice = useCallback(async (id: number) => {
        setLoading(true);
        setError(null);
        try {
            const data = await jsonFetch<{ invoice: InvoiceDetail }>(route('invoices.show', id));
            setInvoice(data.invoice);
        } finally {
            setLoading(false);
        }
    }, []);

    const loadExpiredBonus = useCallback(async (id: number) => {
        setLoading(true);
        setError(null);
        try {
            const data = await jsonFetch<{ invoice: InvoiceDetail }>(route('customer-bonuses.show', id));
            setInvoice(data.invoice);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            setInvoice(null);
            setVisitsPayload(null);
            setActiveInvoiceId(null);
            setActiveExpiredBonusId(null);

            return;
        }

        setView('detail');
        setMessage(null);
        setError(null);
        setBonusChildrenCount('');
        setBonusSessionDurationId(null);
        setBonusError(null);
        setExtendDurationId(null);
        setExtendSubmitting(false);
        setEditingField(null);
        setFieldError(null);
        setActiveInvoiceId(invoiceId);
        setActiveExpiredBonusId(expiredBonusId ?? null);
    }, [open, invoiceId, expiredBonusId]);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (activeExpiredBonusId) {
            loadExpiredBonus(activeExpiredBonusId);
        } else if (activeInvoiceId) {
            loadInvoice(activeInvoiceId);
        }
    }, [open, activeInvoiceId, activeExpiredBonusId, loadInvoice, loadExpiredBonus]);

    const handleVisitClick = useCallback((visit: PreviousVisitInvoice) => {
        if (visit.is_expired_bonus) {
            setActiveInvoiceId(null);
            setActiveExpiredBonusId(visit.id);
        } else {
            setActiveExpiredBonusId(null);
            setActiveInvoiceId(visit.id);
        }

        setView('detail');
    }, []);

    const loadVisitsPage = useCallback(
        async (page: number, initialLoad: boolean) => {
            if (!activeInvoiceId && !activeExpiredBonusId) {
                return;
            }

            if (initialLoad) {
                setVisitsLoading(true);
                setVisitsPayload(null);
                setView('visits');
            } else {
                setVisitsPageLoading(true);
            }

            setError(null);

            try {
                const baseUrl = activeExpiredBonusId
                    ? route('customer-bonuses.visits', activeExpiredBonusId)
                    : route('invoices.visits', activeInvoiceId!);
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('page', String(page));

                const data = await jsonFetch<PreviousVisitsPayload>(url.toString());
                setVisitsPayload(data);
            } catch {
                if (initialLoad) {
                    setError('لا تملك صلاحية عرض الزيارات السابقة.');
                    setView('detail');
                }
            } finally {
                setVisitsLoading(false);
                setVisitsPageLoading(false);
            }
        },
        [activeExpiredBonusId, activeInvoiceId],
    );

    const loadVisits = () => void loadVisitsPage(1, true);

    const submitBonus = async (event: FormEvent) => {
        event.preventDefault();
        if (!activeInvoiceId || !bonusSessionDurationId || bonusChildrenCount === '') {
            return;
        }
        setBonusSubmitting(true);
        setBonusError(null);
        try {
            const data = await jsonFetch<{ message: string; invoice: InvoiceDetail }>(route('invoices.bonuses.store', activeInvoiceId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    children_count: bonusChildrenCount,
                    session_duration_id: bonusSessionDurationId,
                }),
            });
            setInvoice(data.invoice);
            setMessage(data.message);
            setView('detail');
            flushPrefetchedListPages();
            router.reload({ only: ['invoices', 'summary', 'pendingBonusCount', 'eligibleBonusWithoutPendingCount'], preserveScroll: true });
        } catch (err) {
            const validationError = err as ValidationError;
            const messages = Object.values(validationError.errors ?? {}).flat();
            setBonusError(messages[0] ?? 'تعذر إضافة المكافأة.');
        } finally {
            setBonusSubmitting(false);
        }
    };

    const openBonusForm = () => {
        if (!invoice) {
            return;
        }
        setBonusChildrenCount('');
        setBonusSessionDurationId(null);
        setBonusError(null);
        setView('bonus');
    };

    const checkout = async () => {
        if (!activeInvoiceId) {
            return;
        }

        const label = invoice?.invoice_display_label || invoice?.invoice_number || 'هذه الفاتورة';

        const confirmed = await confirm({
            title: 'تسجيل الخروج',
            description: `تسجيل خروج «${label}» وإنهاء الجلسة؟`,
            confirmLabel: 'تسجيل الخروج',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(route('invoices.checkout', activeInvoiceId), {}, {
            preserveScroll: true,
            reset: ['invoices', 'summary'],
            onSuccess: () => {
                flushPrefetchedListPages();
                onOpenChange(false);
            },
        });
    };

    const reactivate = () => {
        if (!activeInvoiceId) {
            return;
        }
        router.patch(route('invoices.reactivate', activeInvoiceId), {}, {
            preserveScroll: true,
            reset: ['invoices', 'summary'],
            onSuccess: () => {
                flushPrefetchedListPages();
                onOpenChange(false);
            },
        });
    };

    const deleteFromRecord = async () => {
        if (!invoice) {
            return;
        }

        const label = invoice.invoice_display_label || invoice.invoice_number;

        const confirmed = await confirm({
            title: 'حذف من السجل',
            description: `حذف «${label}» من الفواتير السابقة؟ لا يمكن التراجع.`,
            confirmLabel: 'حذف',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        const deleteOptions = {
            preserveScroll: true,
            reset: ['invoices'] as const,
            onSuccess: () => {
                flushPrefetchedListPages();
                onOpenChange(false);
            },
        };

        if (invoice.is_expired_bonus && activeExpiredBonusId) {
            router.delete(route('customer-bonuses.completed-record.destroy', activeExpiredBonusId), deleteOptions);

            return;
        }

        if (activeInvoiceId) {
            router.delete(route('invoices.completed-record.destroy', activeInvoiceId), deleteOptions);
        }
    };

    const startEdit = (field: EditableFieldKey) => {
        if (!invoice || !canUpdateInvoiceField(invoice, field)) {
            return;
        }

        setEditingField(field);
        setFieldError(null);
        setEditValue(String(field === 'children_count' ? invoice.children_count : field === 'invoice_number' ? invoice.invoice_number : invoice.phone_number));
    };

    const cancelEdit = () => {
        setEditingField(null);
        setFieldError(null);
        setEditValue('');
    };

    const copyPhoneNumber = async (phoneNumber: string) => {
        try {
            await navigator.clipboard.writeText(phoneNumber);
            setMessage('تم نسخ رقم الجوال');
            setError(null);
        } catch {
            setError('تعذر نسخ رقم الجوال');
        }
    };

    const saveField = async () => {
        const invoiceId = activeInvoiceId ?? invoice?.id ?? null;

        if (!invoiceId || !editingField || !invoice) {
            return;
        }

        if (!canUpdateInvoiceField(invoice, editingField)) {
            setFieldError('لا يمكن تعديل هذا الحقل لهذا النوع من الفواتير.');

            return;
        }

        setFieldSaving(true);
        setFieldError(null);

        const payload =
            editingField === 'children_count'
                ? { children_count: Number(editValue) }
                : { [editingField]: editValue };

        try {
            const data = await jsonFetch<{ message: string; invoice: InvoiceDetail }>(route('invoices.update', invoiceId), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            setInvoice(data.invoice);
            setMessage(data.message);
            setEditingField(null);
            setEditValue('');
            flushPrefetchedListPages();
            router.reload({ only: ['invoices', 'summary'], preserveScroll: true });
        } catch (err) {
            const validationError = err as ValidationError;
            const messages = validationError.errors?.[editingField];
            setFieldError(messages?.[0] ?? validationError.message ?? 'تعذر حفظ التعديل.');
        } finally {
            setFieldSaving(false);
        }
    };

    const extend = () => {
        if (!activeInvoiceId || !extendDurationId || extendSubmitting) {
            return;
        }

        setExtendSubmitting(true);
        router.post(route('invoices.extend', activeInvoiceId), { session_duration_id: extendDurationId }, {
            preserveScroll: true,
            reset: ['invoices'],
            onSuccess: () => {
                flushPrefetchedListPages();
                loadInvoice(activeInvoiceId);
                setView('detail');
            },
            onFinish: () => setExtendSubmitting(false),
        });
    };

    const viewMeta: Record<DialogView, { title: string; icon: LucideIcon }> = {
        detail: { title: invoice?.is_expired_bonus ? 'تفاصيل المكافأة' : 'تفاصيل الفاتورة', icon: Receipt },
        visits: { title: 'الزيارات السابقة', icon: History },
        bonus: { title: 'إضافة مكافأة', icon: Gift },
        extend: { title: 'تمديد الجلسة', icon: Timer },
    };

    const startDateTime = splitDateTime(invoice?.entered_at_full);
    const endDateTime = splitEndDateTime(invoice);
    const canExtend = !invoice?.is_expired_bonus && invoice?.status === 'active' && invoice.expected_exit_at_full !== null;
    const canAddBonus =
        isAdmin &&
        invoice !== null &&
        !invoice.is_expired_bonus &&
        !invoice.is_credit &&
        !invoice.has_pending_bonus &&
        (invoice.status === 'active' || invoice.status === 'completed');
    const canShowReceptionActions =
        showReceptionActions && !invoice?.is_expired_bonus && invoice?.status === 'active';
    const canShowReactivate = showReactivate && !invoice?.is_expired_bonus && invoice?.status === 'completed';
    const canShowDeleteCompleted = showDeleteCompleted && invoice !== null;
    const currentView = viewMeta[view];
    const ViewIcon = currentView.icon;
    const showDetailFooter =
        view === 'detail' &&
        !loading &&
        invoice !== null &&
        (canShowReceptionActions || canShowReactivate || canShowDeleteCompleted);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'flex max-h-[min(calc(100dvh-2rem),90vh)] flex-col gap-0 overflow-hidden p-0 [&>button.absolute]:hidden',
                    view === 'visits' ? 'md:w-[50vw] md:max-w-[50vw]' : 'raad-dialog-surface-lg',
                )}
            >
                <DialogHeader className="shrink-0 border-b px-4 py-2.5 text-right md:py-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 md:gap-2">
                            {view !== 'detail' && (
                                <Button type="button" variant="ghost" size="icon" onClick={() => setView('detail')}>
                                    <ArrowRight className="size-4" />
                                </Button>
                            )}
                            <DialogTitle className="flex items-center gap-1.5 text-sm md:gap-2 md:text-base">
                                <ViewIcon className="text-primary size-4 shrink-0" />
                                {currentView.title}
                            </DialogTitle>
                            {view === 'detail' && invoice && (
                                <Badge variant="secondary" className="shrink-0 text-xs">
                                    {invoice.status_label}
                                </Badge>
                            )}
                            {view === 'detail' && displayInvoice?.status === 'active' && !displayInvoice.is_expired_bonus && (
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        'shrink-0 border-0 text-xs md:hidden',
                                        remainingTimeBadgeClass(displayInvoice.remaining_minutes),
                                    )}
                                >
                                    <Timer className="size-3" />
                                    {formatRemainingMinutes(displayInvoice.remaining_minutes)}
                                </Badge>
                            )}
                        </div>
                        <div className="flex shrink-0 items-center gap-0.5 md:gap-1">
                            {isAdmin && view === 'detail' && invoice && (
                                <>
                                    {canAddBonus && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            title="إضافة مكافأة"
                                            className="text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-700 dark:text-emerald-400"
                                            onClick={openBonusForm}
                                        >
                                            <Gift className="size-4" />
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        title="الزيارات السابقة"
                                        className="text-amber-600 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400"
                                        onClick={loadVisits}
                                    >
                                        <Star className="size-4 fill-amber-400 text-amber-500" />
                                    </Button>
                                </>
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground"
                                onClick={() => onOpenChange(false)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3 md:p-4">
                    {loading && <DetailLoadingSkeleton />}

                    {!loading && invoice && view === 'detail' && (
                        <div className="space-y-3">
                            <div className="grid grid-cols-2 gap-2 md:gap-3">
                                {invoice.is_expired_bonus ? (
                                    <InfoCard label="المكافأة" icon={Gift}>
                                        <p className="text-xl font-bold text-rose-600 md:text-2xl dark:text-rose-400">
                                            {invoice.invoice_display_label}
                                        </p>
                                    </InfoCard>
                                ) : invoice.is_credit ? (
                                    <InfoCard label="الرصيد" icon={Wallet}>
                                        <p className="text-xl font-bold text-sky-600 md:text-2xl dark:text-sky-400">
                                            {invoice.invoice_display_label}
                                        </p>
                                    </InfoCard>
                                ) : invoice.is_bonus ? (
                                    <InfoCard label="المكافأة" icon={Gift}>
                                        <p className="text-xl font-bold text-emerald-600 md:text-2xl dark:text-emerald-400">
                                            {invoice.invoice_display_label}
                                        </p>
                                    </InfoCard>
                                ) : (
                                    <EditableInvoiceField
                                        label="رقم الفاتورة"
                                        field="invoice_number"
                                        canEdit={canUpdateInvoiceField(invoice, 'invoice_number')}
                                        displayValue={
                                            <p className="text-primary text-xl font-bold md:text-2xl" dir="ltr">
                                                {invoice.invoice_number}
                                            </p>
                                        }
                                        editingField={editingField}
                                        editValue={editValue}
                                        fieldError={fieldError}
                                        saving={fieldSaving}
                                        onStartEdit={startEdit}
                                        onCancelEdit={cancelEdit}
                                        onEditValueChange={setEditValue}
                                        onSave={saveField}
                                    />
                                )}

                                {invoice.is_expired_bonus ? (
                                    <InfoCard label="عدد الأطفال">
                                        <p className="text-xl font-bold text-emerald-600 md:text-2xl dark:text-emerald-400">{invoice.children_count}</p>
                                    </InfoCard>
                                ) : (
                                    <EditableInvoiceField
                                        label="عدد الأطفال"
                                        field="children_count"
                                        canEdit={canUpdateInvoiceField(invoice, 'children_count')}
                                        displayValue={
                                            <p className="text-xl font-bold text-emerald-600 md:text-2xl dark:text-emerald-400">{invoice.children_count}</p>
                                        }
                                        editingField={editingField}
                                        editValue={editValue}
                                        fieldError={fieldError}
                                        saving={fieldSaving}
                                        inputType="number"
                                        onStartEdit={startEdit}
                                        onCancelEdit={cancelEdit}
                                        onEditValueChange={setEditValue}
                                        onSave={saveField}
                                    />
                                )}

                                <InfoCard label="رقم الجوال">
                                    {invoice.is_expired_bonus || editingField !== 'phone_number' ? (
                                        <div className="group space-y-2">
                                            <div className="flex items-start justify-between gap-2">
                                                <button
                                                    type="button"
                                                    className="hover:text-primary cursor-pointer text-base font-semibold transition-colors md:text-lg"
                                                    dir="ltr"
                                                    title="انقر للنسخ"
                                                    onClick={() => copyPhoneNumber(invoice.phone_number)}
                                                >
                                                    {invoice.phone_number}
                                                </button>
                                                {!invoice.is_expired_bonus && canUpdateInvoiceField(invoice, 'phone_number') && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        title="تعديل"
                                                        className="text-muted-foreground size-7 shrink-0 opacity-100 md:opacity-70 md:group-hover:opacity-100"
                                                        onClick={() => startEdit('phone_number')}
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                            {isAdmin && !invoice.is_expired_bonus && invoice.has_pending_bonus && (
                                                <Badge className="mt-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                                    مكافأة مضافة — بانتظار التفعيل
                                                </Badge>
                                            )}
                                            {isAdmin && !invoice.is_expired_bonus && invoice.is_bonus_eligible && !invoice.is_bonus && !invoice.has_pending_bonus && (
                                                <Badge className="mt-1 bg-amber-500/15 text-amber-700 dark:text-amber-300">
                                                    مؤهل للمكافأة — {invoice.visit_count}
                                                </Badge>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <PhoneInput
                                                value={editValue}
                                                onChange={(e) => setEditValue(e.target.value)}
                                                className="bg-background"
                                                autoFocus
                                            />
                                            {fieldError && <p className="text-destructive text-xs">{fieldError}</p>}
                                            <div className="flex gap-2">
                                                <Button type="button" size="sm" disabled={fieldSaving} onClick={saveField}>
                                                    {fieldSaving ? <LoaderCircle className="size-3.5 animate-spin" /> : 'حفظ'}
                                                </Button>
                                                <Button type="button" size="sm" variant="ghost" disabled={fieldSaving} onClick={cancelEdit}>
                                                    إلغاء
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </InfoCard>

                                <InfoCard label="مدة الجلسة">
                                    <div className="flex flex-col gap-1.5">
                                        <div className="inline-flex w-fit items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                            <Timer className="size-3" />
                                            {sessionDurationLabel(invoice)}
                                        </div>
                                        {invoice.extensions.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {invoice.extensions.map((ext) => (
                                                    <SessionBadge
                                                        key={ext.id}
                                                        name={ext.session_duration.name}
                                                        color={ext.session_duration.color}
                                                    />
                                                ))}
                                            </div>
                                        )}
                                        {displayInvoice?.status === 'active' && (
                                            <p
                                                className={cn(
                                                    'hidden text-sm font-medium md:block',
                                                    displayInvoice.remaining_minutes === null
                                                        ? 'text-muted-foreground'
                                                        : remainingTimeTextClass(displayInvoice.remaining_minutes),
                                                )}
                                            >
                                                متبقي: {formatRemainingMinutes(displayInvoice.remaining_minutes)}
                                            </p>
                                        )}
                                    </div>
                                </InfoCard>

                                <DateTimeCard
                                    label={invoice.is_expired_bonus ? 'تاريخ الإضافة' : 'وقت البداية'}
                                    dateTime={startDateTime}
                                />
                                <DateTimeCard
                                    label={invoice.is_expired_bonus ? 'انتهت في' : 'وقت النهاية'}
                                    dateTime={endDateTime}
                                />
                            </div>

                            {message && (
                                <Alert className="py-2">
                                    <AlertDescription className="text-sm">{message}</AlertDescription>
                                </Alert>
                            )}
                            {error && (
                                <Alert variant="destructive" className="py-2">
                                    <AlertDescription className="text-sm">{error}</AlertDescription>
                                </Alert>
                            )}

                        </div>
                    )}

                    {view === 'visits' && (
                        <PreviousVisitsPanel
                            payload={visitsPayload}
                            loading={visitsLoading}
                            pageLoading={visitsPageLoading}
                            currentInvoiceId={activeInvoiceId}
                            currentExpiredBonusId={activeExpiredBonusId}
                            onVisitClick={handleVisitClick}
                            onPageChange={(nextPage) => void loadVisitsPage(nextPage, false)}
                        />
                    )}

                    {view === 'bonus' && (
                        <form onSubmit={submitBonus} className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                إضافة مكافأة لـ <span dir="ltr">{invoice?.phone_number}</span>
                                {invoice && (
                                    <span className="text-foreground font-medium"> — {invoice.visit_count}</span>
                                )}
                            </p>
                            <div className="space-y-2">
                                <Label htmlFor="bonus_children_count">عدد الأطفال</Label>
                                <NumericInput
                                    id="bonus_children_count"
                                    maxLength={2}
                                    value={bonusChildrenCount}
                                    onChange={(e) =>
                                        setBonusChildrenCount(e.target.value === '' ? '' : Number(e.target.value))
                                    }
                                    placeholder="أدخل عدد الأطفال"
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>مدة جلسة المكافأة</Label>
                                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                                    {sessionDurations.map((session) => (
                                        <SessionCard
                                            key={session.id}
                                            session={session}
                                            compact
                                            boxShadowActive={bonusSessionDurationId === session.id}
                                            onClick={() => setBonusSessionDurationId(session.id)}
                                        />
                                    ))}
                                </div>
                            </div>
                            {bonusError && (
                                <Alert variant="destructive" className="py-2">
                                    <AlertDescription className="text-sm">{bonusError}</AlertDescription>
                                </Alert>
                            )}
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={bonusSubmitting || !bonusSessionDurationId || bonusChildrenCount === ''}
                                    size="sm"
                                >
                                    {bonusSubmitting ? <LoaderCircle className="size-4 animate-spin" /> : 'إضافة المكافأة'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}

                    {view === 'extend' && (
                        <div className="space-y-3">
                            <p className="text-muted-foreground text-sm">اختر مدة التمديد</p>
                            <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                                {sessionDurations.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        compact
                                        boxShadowActive={extendDurationId === session.id}
                                        onClick={() => setExtendDurationId(session.id)}
                                    />
                                ))}
                            </div>
                            <div className="border-t pt-3">
                                <Button
                                    type="button"
                                    className="min-h-11 w-full"
                                    disabled={extendSubmitting || !extendDurationId}
                                    onClick={extend}
                                >
                                    {extendSubmitting ? <LoaderCircle className="size-4 animate-spin" /> : 'تمديد'}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {showDetailFooter && invoice && (
                    <div className="shrink-0 space-y-2 border-t bg-background p-4">
                        {canShowReceptionActions && (
                            <div className={cn('flex gap-2', canExtend ? 'flex-row' : 'flex-col')}>
                                {canExtend && (
                                    <Button
                                        type="button"
                                        className="min-h-11 flex-1"
                                        variant="secondary"
                                        onClick={() => setView('extend')}
                                    >
                                        <Timer className="size-4" />
                                        تمديد
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    className={cn('min-h-11', canExtend ? 'flex-1' : 'w-full')}
                                    variant="destructive"
                                    onClick={checkout}
                                >
                                    <LogOut className="size-4" />
                                    خروج
                                </Button>
                            </div>
                        )}
                        {(canShowReactivate || canShowDeleteCompleted) && (
                            <div className="flex flex-row gap-2">
                                {canShowReactivate && (
                                    <Button type="button" className="min-h-11 flex-1" variant="secondary" onClick={reactivate}>
                                        <RotateCcw className="size-4" />
                                        إعادة تفعيل
                                    </Button>
                                )}
                                {canShowDeleteCompleted && (
                                    <Button type="button" className="min-h-11 flex-1" variant="destructive" onClick={deleteFromRecord}>
                                        <Trash2 className="size-4" />
                                        حذف من السجل
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
