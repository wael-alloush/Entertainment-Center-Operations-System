import { SessionCard } from '@/components/raad/session-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRemainingMinutes } from '@/lib/format-time';
import { cn } from '@/lib/utils';
import { InvoiceDetail, PreviousVisitsPayload, SessionDuration } from '@/types/domain';
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
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState, type ReactNode } from 'react';

type DialogView = 'detail' | 'visits' | 'bonus' | 'extend';
type EditableFieldKey = 'invoice_number' | 'phone_number' | 'children_count';

interface ValidationError extends Error {
    errors?: Record<string, string[]>;
}

interface InvoiceDetailDialogProps {
    invoiceId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isAdmin?: boolean;
    showReceptionActions?: boolean;
    showReactivate?: boolean;
    sessionDurations?: SessionDuration[];
}

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function jsonFetch<T>(url: string, options?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options?.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const data = (await response.json().catch(() => ({}))) as T & { errors?: Record<string, string[]> };

    if (!response.ok) {
        const error = new Error('Request failed') as ValidationError;
        error.errors = data.errors;
        throw error;
    }

    return data;
}

function splitDateTime(value: string | null | undefined): { date: string; time: string } {
    if (!value) {
        return { date: '—', time: '—' };
    }

    const [date, ...timeParts] = value.split(' ');

    return { date, time: timeParts.join(' ') || '—' };
}

function splitEndDateTime(invoice: InvoiceDetail | null): { date: string; time: string } {
    const value = invoice?.expected_exit_at_full ?? invoice?.exited_at_full;

    if (value) {
        return splitDateTime(value);
    }

    if (invoice?.status === 'active') {
        return { date: 'مفتوح', time: 'مفتوح' };
    }

    return { date: '—', time: '—' };
}

function sessionDurationLabel(invoice: InvoiceDetail): string {
    const minutes = invoice.session_duration.duration_minutes;

    if (minutes !== null) {
        return `${minutes} دقيقة`;
    }

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
        <div className={cn('space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3', className)}>
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
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-24 w-full rounded-lg" />
            ))}
        </div>
    );
}

function remainingTimeTone(remainingMinutes: number): string {
    if (remainingMinutes <= 10) {
        return 'text-destructive';
    }

    return 'text-muted-foreground';
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
}) {
    const isEditing = editingField === field;

    return (
        <InfoCard label={label} icon={icon}>
            {isEditing ? (
                <div className="space-y-2">
                    <Input
                        type={inputType}
                        value={editValue}
                        onChange={(e) => onEditValueChange(e.target.value)}
                        dir={inputDir}
                        className="bg-background"
                        autoFocus
                    />
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
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="تعديل"
                        className="text-muted-foreground size-8 shrink-0 opacity-70 transition-opacity group-hover:opacity-100"
                        onClick={() => onStartEdit(field)}
                    >
                        <Pencil className="size-3.5" />
                    </Button>
                </div>
            )}
        </InfoCard>
    );
}

function PhoneAdminActions({ onVisits, onBonus }: { onVisits: () => void; onBonus: () => void }) {
    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                title="إضافة مكافأة"
                onClick={onBonus}
                className="flex size-9 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-600 transition-colors hover:bg-emerald-500/20 dark:text-emerald-400"
            >
                <Gift className="size-4" />
            </button>
            <button
                type="button"
                title="الزيارات السابقة"
                onClick={onVisits}
                className="flex size-9 items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-600 transition-colors hover:bg-amber-500/20 dark:text-amber-400"
            >
                <Star className="size-4 fill-amber-400 text-amber-500" />
            </button>
        </div>
    );
}

function VisitValueBadge({ value }: { value: string | number }) {
    return (
        <span className="bg-muted text-foreground inline-flex min-w-10 items-center justify-center rounded-md px-2.5 py-1 text-sm font-medium">
            {value}
        </span>
    );
}

function whatsappUrl(phoneNumber: string): string {
    const normalized = phoneNumber.replace(/\D/g, '').replace(/^0/, '');

    return `https://wa.me/966${normalized}`;
}

function PreviousVisitsPanel({
    payload,
    loading,
    currentInvoiceId,
}: {
    payload: PreviousVisitsPayload | null;
    loading: boolean;
    currentInvoiceId?: number | null;
}) {
    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <LoaderCircle className="text-primary size-8 animate-spin" />
            </div>
        );
    }

    if (!payload || payload.invoices.length === 0) {
        return (
            <div className="text-muted-foreground flex flex-col items-center gap-2 py-12 text-center text-sm">
                <History className="text-muted-foreground/60 size-8" />
                لا توجد فواتير سابقة
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                <div className="flex items-center gap-2" dir="ltr">
                    <Phone className="text-muted-foreground size-4" />
                    <span className="font-mono text-lg font-semibold tracking-wide">{payload.phone_number}</span>
                </div>
                <div className="flex items-center gap-1">
                    <a
                        href={whatsappUrl(payload.phone_number)}
                        target="_blank"
                        rel="noreferrer"
                        className="text-muted-foreground hover:text-foreground p-2"
                        title="واتساب"
                    >
                        <MessageCircle className="size-4" />
                    </a>
                    <a
                        href={`tel:${payload.phone_number}`}
                        className="text-muted-foreground hover:text-foreground p-2"
                        title="اتصال"
                    >
                        <Phone className="size-4" />
                    </a>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div className="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                    <CalendarDays className="text-muted-foreground size-4" />
                    <div>
                        <p className="text-muted-foreground text-xs">أيام الزيارة</p>
                        <p className="text-lg font-semibold">{payload.visit_count}</p>
                    </div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                    <Users className="text-muted-foreground size-4" />
                    <div>
                        <p className="text-muted-foreground text-xs">متوسط الأطفال / زيارة</p>
                        <p className="text-lg font-semibold">{payload.average_children}</p>
                    </div>
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-border/70 shadow-sm">
                <table className="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr className="raad-table-head">
                            <th className="px-3 py-3 text-right font-medium">رقم الفاتورة</th>
                            <th className="px-3 py-3 text-right font-medium">عدد الأطفال</th>
                            <th className="px-3 py-3 text-right font-medium">مدة الجلسة الأصلية</th>
                            <th className="px-3 py-3 text-right font-medium">مدة التمديدات</th>
                            <th className="px-3 py-3 text-right font-medium">إجمالي الوقت</th>
                            <th className="px-3 py-3 text-right font-medium">وقت البداية</th>
                            <th className="px-3 py-3 text-right font-medium">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payload.invoices.map((visit) => (
                            <tr
                                key={visit.id}
                                className={cn(
                                    'border-b transition-colors last:border-0',
                                    visit.id === currentInvoiceId
                                        ? 'bg-primary/8 ring-1 ring-inset ring-primary/20'
                                        : 'hover:bg-muted/30',
                                )}
                            >
                                <td className="px-3 py-3 text-center">
                                    <VisitValueBadge value={visit.invoice_number} />
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <VisitValueBadge value={visit.children_count} />
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <VisitValueBadge value={visit.original_duration_minutes} />
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <VisitValueBadge value={visit.extensions_duration_minutes} />
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <VisitValueBadge value={visit.total_duration_minutes} />
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <span className="bg-muted inline-block min-w-20 rounded-lg px-3 py-1.5 font-medium" dir="ltr">
                                        {visit.started_at ?? '—'}
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-center">
                                    <span className="bg-muted inline-block min-w-28 rounded-lg px-3 py-1.5 font-medium" dir="ltr">
                                        {visit.visit_date ?? '—'}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function DateTimeCard({ label, dateTime }: { label: string; dateTime: { date: string; time: string } }) {
    return (
        <InfoCard label={label}>
            <div className="space-y-2">
                <div className="flex items-center justify-between gap-2 rounded-md border border-border/50 bg-background px-3 py-2.5" dir="ltr">
                    <span className="font-semibold">{dateTime.date}</span>
                    <CalendarDays className="text-primary size-4 shrink-0" />
                </div>
                <div className="flex items-center justify-between gap-2 rounded-md border border-border/50 bg-background px-3 py-2.5" dir="ltr">
                    <span className="font-semibold">{dateTime.time}</span>
                    <Clock className="size-4 shrink-0 text-orange-500" />
                </div>
            </div>
        </InfoCard>
    );
}

export function InvoiceDetailDialog({
    invoiceId,
    open,
    onOpenChange,
    isAdmin = false,
    showReceptionActions = false,
    showReactivate = false,
    sessionDurations = [],
}: InvoiceDetailDialogProps) {
    const [view, setView] = useState<DialogView>('detail');
    const [loading, setLoading] = useState(false);
    const [invoice, setInvoice] = useState<InvoiceDetail | null>(null);
    const [visitsPayload, setVisitsPayload] = useState<PreviousVisitsPayload | null>(null);
    const [visitsLoading, setVisitsLoading] = useState(false);
    const [bonusTitle, setBonusTitle] = useState('');
    const [bonusNotes, setBonusNotes] = useState('');
    const [bonusSubmitting, setBonusSubmitting] = useState(false);
    const [extendDurationId, setExtendDurationId] = useState<number | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [editingField, setEditingField] = useState<EditableFieldKey | null>(null);
    const [editValue, setEditValue] = useState('');
    const [fieldError, setFieldError] = useState<string | null>(null);
    const [fieldSaving, setFieldSaving] = useState(false);

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

    useEffect(() => {
        if (open && invoiceId) {
            setView('detail');
            setMessage(null);
            setError(null);
            setBonusTitle('');
            setBonusNotes('');
            setExtendDurationId(null);
            setEditingField(null);
            setFieldError(null);
            loadInvoice(invoiceId);
        } else {
            setInvoice(null);
            setVisitsPayload(null);
        }
    }, [open, invoiceId, loadInvoice]);

    const loadVisits = async () => {
        if (!invoiceId) {
            return;
        }
        setVisitsLoading(true);
        setError(null);
        setView('visits');
        try {
            const data = await jsonFetch<PreviousVisitsPayload>(route('invoices.visits', invoiceId));
            setVisitsPayload(data);
        } catch {
            setError('لا تملك صلاحية عرض الزيارات السابقة.');
            setView('detail');
        } finally {
            setVisitsLoading(false);
        }
    };

    const submitBonus = async (event: FormEvent) => {
        event.preventDefault();
        if (!invoiceId) {
            return;
        }
        setBonusSubmitting(true);
        setError(null);
        try {
            const data = await jsonFetch<{ message: string; invoice: InvoiceDetail }>(route('invoices.bonuses.store', invoiceId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify({ title: bonusTitle, notes: bonusNotes || null }),
            });
            setInvoice(data.invoice);
            setMessage(data.message);
            setView('detail');
        } catch {
            setError('لا تملك صلاحية إضافة مكافأة.');
            setView('detail');
        } finally {
            setBonusSubmitting(false);
        }
    };

    const checkout = () => {
        if (!invoiceId) {
            return;
        }
        router.patch(route('invoices.checkout', invoiceId), {}, { onSuccess: () => onOpenChange(false) });
    };

    const reactivate = () => {
        if (!invoiceId) {
            return;
        }
        router.patch(route('invoices.reactivate', invoiceId), {}, { onSuccess: () => onOpenChange(false) });
    };

    const startEdit = (field: EditableFieldKey) => {
        if (!invoice) {
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
        if (!invoiceId || !editingField) {
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(payload),
            });
            setInvoice(data.invoice);
            setMessage(data.message);
            setEditingField(null);
            setEditValue('');
            router.reload({ only: ['invoices', 'summary'], preserveScroll: true });
        } catch (err) {
            const validationError = err as ValidationError;
            const messages = validationError.errors?.[editingField];
            setFieldError(messages?.[0] ?? 'تعذر حفظ التعديل.');
        } finally {
            setFieldSaving(false);
        }
    };

    const extend = () => {
        if (!invoiceId || !extendDurationId) {
            return;
        }
        router.post(route('invoices.extend', invoiceId), { session_duration_id: extendDurationId }, {
            preserveScroll: true,
            reset: ['invoices'],
            onSuccess: () => {
                loadInvoice(invoiceId);
                setView('detail');
            },
        });
    };

    const viewMeta: Record<DialogView, { title: string; icon: LucideIcon }> = {
        detail: { title: 'تفاصيل الفاتورة', icon: Receipt },
        visits: { title: 'الزيارات السابقة', icon: Star },
        bonus: { title: 'إضافة مكافأة', icon: Gift },
        extend: { title: 'تمديد الجلسة', icon: Timer },
    };

    const startDateTime = splitDateTime(invoice?.entered_at_full);
    const endDateTime = splitEndDateTime(invoice);
    const canExtend = invoice?.status === 'active' && invoice.expected_exit_at_full !== null;
    const currentView = viewMeta[view];
    const ViewIcon = currentView.icon;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-2xl gap-0 overflow-y-auto p-0 [&>button.absolute]:hidden">
                <DialogHeader className="border-b px-4 py-3 text-right">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex min-w-0 flex-1 items-center gap-2">
                            {view !== 'detail' && (
                                <Button type="button" variant="ghost" size="icon" onClick={() => setView('detail')}>
                                    <ArrowRight className="size-4" />
                                </Button>
                            )}
                            <DialogTitle className="flex items-center gap-2 text-base">
                                <ViewIcon className="text-primary size-4" />
                                {currentView.title}
                            </DialogTitle>
                            {view === 'detail' && invoice && (
                                <Badge variant="secondary" className="text-xs">
                                    {invoice.status_label}
                                </Badge>
                            )}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground shrink-0"
                            onClick={() => onOpenChange(false)}
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                </DialogHeader>

                <div className="space-y-3 p-4">
                    {loading && <DetailLoadingSkeleton />}

                    {!loading && invoice && view === 'detail' && (
                        <div className="space-y-3">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <EditableInvoiceField
                                    label="رقم الفاتورة"
                                    field="invoice_number"
                                    displayValue={
                                        <p className="text-primary text-2xl font-bold" dir="ltr">
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

                                <EditableInvoiceField
                                    label="عدد الأطفال"
                                    field="children_count"
                                    displayValue={
                                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{invoice.children_count}</p>
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

                                <InfoCard label="رقم الجوال">
                                    {editingField === 'phone_number' ? (
                                        <div className="space-y-2">
                                            <Input
                                                value={editValue}
                                                onChange={(e) => setEditValue(e.target.value)}
                                                dir="ltr"
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
                                    ) : (
                                        <div className="group space-y-2">
                                            <div className="flex items-start justify-between gap-2">
                                                <button
                                                    type="button"
                                                    className="hover:text-primary cursor-pointer text-lg font-semibold transition-colors"
                                                    dir="ltr"
                                                    title="انقر للنسخ"
                                                    onClick={() => copyPhoneNumber(invoice.phone_number)}
                                                >
                                                    {invoice.phone_number}
                                                </button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    title="تعديل"
                                                    className="text-muted-foreground size-7 shrink-0 opacity-70 group-hover:opacity-100"
                                                    onClick={() => startEdit('phone_number')}
                                                >
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                            </div>
                                            {isAdmin && (
                                                <div className="pt-1">
                                                    <PhoneAdminActions onVisits={loadVisits} onBonus={() => setView('bonus')} />
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </InfoCard>

                                <InfoCard label="مدة الجلسة">
                                    <div className="space-y-2">
                                        <div className="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1.5 text-sm font-semibold text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                            <Timer className="size-3.5" />
                                            {sessionDurationLabel(invoice)}
                                        </div>
                                        {invoice.extensions.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                {invoice.extensions.map((ext) => (
                                                    <SessionBadge
                                                        key={ext.id}
                                                        name={ext.session_duration.name}
                                                        color={ext.session_duration.color}
                                                    />
                                                ))}
                                            </div>
                                        )}
                                        {invoice.status === 'active' && (
                                            <p
                                                className={cn(
                                                    'text-sm font-medium',
                                                    invoice.remaining_minutes === null
                                                        ? 'text-muted-foreground'
                                                        : remainingTimeTone(invoice.remaining_minutes),
                                                )}
                                            >
                                                متبقي: {formatRemainingMinutes(invoice.remaining_minutes)}
                                            </p>
                                        )}
                                    </div>
                                </InfoCard>

                                <DateTimeCard label="وقت البداية" dateTime={startDateTime} />
                                <DateTimeCard label="وقت النهاية" dateTime={endDateTime} />
                            </div>

                            {invoice.bonuses.length > 0 && (
                                <div className="space-y-1.5 rounded-lg border border-border/60 bg-muted/20 p-3">
                                    <p className="text-sm font-medium">المكافآت ({invoice.bonus_count})</p>
                                    {invoice.bonuses.map((bonus) => (
                                        <p key={bonus.id} className="text-muted-foreground text-sm">
                                            <span className="text-foreground font-medium">{bonus.title}</span>
                                            {bonus.notes ? ` — ${bonus.notes}` : ''}
                                        </p>
                                    ))}
                                </div>
                            )}

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

                            {showReceptionActions && (
                                <div className={cn('grid gap-2 border-t pt-3', canExtend ? 'grid-cols-2' : 'grid-cols-1')}>
                                    {canExtend && (
                                        <Button type="button" size="sm" variant="secondary" onClick={() => setView('extend')}>
                                            <Timer className="size-4" />
                                            تمديد
                                        </Button>
                                    )}
                                    <Button type="button" size="sm" variant="destructive" onClick={checkout}>
                                        <LogOut className="size-4" />
                                        خروج
                                    </Button>
                                </div>
                            )}
                            {showReactivate && invoice.status === 'completed' && (
                                <div className="flex justify-center border-t pt-3">
                                    <Button type="button" size="sm" variant="secondary" onClick={reactivate}>
                                        <RotateCcw className="size-4" />
                                        إعادة تفعيل
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}

                    {view === 'visits' && (
                        <PreviousVisitsPanel payload={visitsPayload} loading={visitsLoading} currentInvoiceId={invoiceId} />
                    )}

                    {view === 'bonus' && (
                        <form onSubmit={submitBonus} className="space-y-3">
                            <p className="text-muted-foreground text-sm">
                                إضافة مكافأة لـ <span dir="ltr">{invoice?.phone_number}</span>
                            </p>
                            <div className="space-y-2">
                                <Label htmlFor="bonus_title">عنوان المكافأة</Label>
                                <Input id="bonus_title" value={bonusTitle} onChange={(e) => setBonusTitle(e.target.value)} required />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bonus_notes">ملاحظات</Label>
                                <textarea
                                    id="bonus_notes"
                                    className="border-input bg-background flex min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                    value={bonusNotes}
                                    onChange={(e) => setBonusNotes(e.target.value)}
                                />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={bonusSubmitting} size="sm">
                                    {bonusSubmitting ? <LoaderCircle className="size-4 animate-spin" /> : 'حفظ المكافأة'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}

                    {view === 'extend' && (
                        <div className="space-y-3">
                            <p className="text-muted-foreground text-sm">اختر مدة التمديد</p>
                            <div className="grid grid-cols-2 gap-3">
                                {sessionDurations.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        boxShadowActive={extendDurationId === session.id}
                                        onClick={() => setExtendDurationId(session.id)}
                                    />
                                ))}
                            </div>
                            <div className="flex justify-center border-t pt-3">
                                <Button type="button" size="sm" disabled={!extendDurationId} onClick={extend}>
                                    تمديد
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
