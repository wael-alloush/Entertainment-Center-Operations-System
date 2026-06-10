import { InvoiceDetailDialog } from '@/components/raad/invoice-detail-dialog';
import { SessionCard } from '@/components/raad/session-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatExitTime, formatRemainingMinutes } from '@/lib/format-time';
import { cn } from '@/lib/utils';
import { Auth } from '@/types';
import { Skeleton } from '@/components/ui/skeleton';
import { useActiveInvoiceSync } from '@/hooks/use-active-invoice-sync';
import { ActiveInvoiceChanges, Invoice, InvoiceFilters, InvoiceSummary, PaginatedInvoices, SessionDuration } from '@/types/domain';
import { router, useForm, usePage, WhenVisible } from '@inertiajs/react';
import {
    Baby,
    CalendarDays,
    Gamepad2,
    Hash,
    History,
    Phone,
    PhoneCall,
    PlayCircle,
    Plus,
    Search,
    Timer,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

const defaultFilters: InvoiceFilters = {
    search: '',
    from_date: '',
    to_date: '',
};

function filtersToParams(filters: InvoiceFilters): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.search) {
        params.search = filters.search;
    }

    if (filters.from_date) {
        params.from_date = filters.from_date;
    }

    if (filters.to_date) {
        params.to_date = filters.to_date;
    }

    return params;
}

function hasActiveFilters(filters: InvoiceFilters): boolean {
    return Boolean(filters.search || filters.from_date || filters.to_date);
}

interface InvoicesTableProps {
    invoices: PaginatedInvoices;
    sessionDurations: SessionDuration[];
    summary: InvoiceSummary;
    filters: InvoiceFilters;
    variant: 'active' | 'completed';
    routeName: 'invoices.active' | 'invoices.completed';
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

export function InvoicesTable({ invoices, sessionDurations, summary, filters, variant, routeName }: InvoicesTableProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [invoiceRows, setInvoiceRows] = useState(invoices.data);
    const [displaySummary, setDisplaySummary] = useState(summary);
    const [createOpen, setCreateOpen] = useState(false);
    const [detailInvoiceId, setDetailInvoiceId] = useState<number | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [selectedSessionTime, setSelectedSessionTime] = useState<number | null>(null);
    const [localFilters, setLocalFilters] = useState(filters);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        invoice_number: '',
        phone_number: '',
        children_count: 1,
        session_duration_id: 0,
    });

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    useEffect(() => {
        setInvoiceRows(invoices.data);
        setDisplaySummary(summary);
    }, [invoices.data, summary]);

    const syncResetKey = invoices.data.map((invoice) => invoice.id).join(',');

    const applyInvoiceChanges = useCallback((payload: ActiveInvoiceChanges) => {
        setInvoiceRows((currentRows) => {
            let nextRows = currentRows.filter((invoice) => !payload.removed_ids.includes(invoice.id));

            for (const invoice of payload.updated) {
                const existingIndex = nextRows.findIndex((row) => row.id === invoice.id);

                if (existingIndex >= 0) {
                    nextRows[existingIndex] = invoice;
                } else {
                    nextRows = [invoice, ...nextRows];
                }
            }

            return nextRows;
        });

        if (payload.summary) {
            setDisplaySummary(payload.summary);
        }
    }, []);

    useActiveInvoiceSync(variant === 'active' && !hasActiveFilters(localFilters), applyInvoiceChanges, syncResetKey);

    useEffect(() => {
        return () => {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }
        };
    }, []);

    const pushFilters = useCallback(
        (nextFilters: InvoiceFilters) => {
            router.get(route(routeName), filtersToParams(nextFilters), {
                preserveState: true,
                replace: true,
                reset: ['invoices'],
            });
        },
        [routeName],
    );

    const updateFilters = (patch: Partial<InvoiceFilters>, options?: { debounceSearch?: boolean }) => {
        const nextFilters = { ...localFilters, ...patch };
        setLocalFilters(nextFilters);

        if (options?.debounceSearch) {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }

            searchDebounceRef.current = setTimeout(() => pushFilters(nextFilters), 350);

            return;
        }

        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        pushFilters(nextFilters);
    };

    const clearFilters = () => {
        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        setLocalFilters(defaultFilters);
        router.get(route(routeName), {}, { preserveState: true, replace: true, reset: ['invoices'] });
    };

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        post(route('invoices.store'), {
            onSuccess: () => {
                reset();
                setCreateOpen(false);
                setSelectedSessionTime(null);
            },
        });
    };

    const openDetail = (invoice: Invoice) => {
        setDetailInvoiceId(invoice.id);
        setDetailOpen(true);
    };

    const toggleParentCall = (event: React.MouseEvent, invoice: Invoice) => {
        event.stopPropagation();
        router.patch(route('invoices.parent-call', invoice.id), {}, { preserveScroll: true, reset: ['invoices'] });
    };

    const title = variant === 'active' ? 'الفواتير النشطة' : 'الفواتير السابقة';
    const description =
        variant === 'active'
            ? 'الفواتير الجارية — يتم مزامنة التغييرات تلقائياً'
            : 'سجل الفواتير المكتملة والمغلقة';

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <span
                            className={cn(
                                'raad-icon-chip',
                                variant === 'active'
                                    ? 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-violet-500/12 text-violet-600 dark:text-violet-400',
                            )}
                        >
                            {variant === 'active' ? (
                                <PlayCircle className="size-5" strokeWidth={2.25} />
                            ) : (
                                <History className="size-5" strokeWidth={2.25} />
                            )}
                        </span>
                        {title}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">{description}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {variant === 'active' && (
                        <>
                            <Badge variant="secondary" className="gap-1.5 bg-primary/10 text-primary">
                                <Gamepad2 className="size-3.5" />
                                {displaySummary.invoice_count} فاتورة
                            </Badge>
                            <Badge variant="secondary" className="gap-1.5 bg-rose-500/10 text-rose-700 dark:text-rose-300">
                                <Baby className="size-3.5" />
                                {displaySummary.children_count} طفل
                            </Badge>
                        </>
                    )}
                    {variant === 'active' && (
                        <Button type="button" size="sm" className="shadow-md shadow-primary/20" onClick={() => setCreateOpen(true)}>
                            <Plus className="size-4" />
                            فاتورة جديدة
                        </Button>
                    )}
                </div>
            </div>

            <Card className="raad-card-surface">
                <CardHeader className="space-y-4 border-b pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle className="text-base">قائمة الفواتير</CardTitle>
                            <CardDescription>
                                {invoiceRows.length} من {invoices.total} نتيجة
                            </CardDescription>
                        </div>
                        {hasActiveFilters(localFilters) && (
                            <Button type="button" variant="ghost" size="sm" className="text-muted-foreground" onClick={clearFilters}>
                                <X className="size-4" />
                                مسح الفلاتر
                            </Button>
                        )}
                    </div>
                    <div
                        className={cn(
                            'grid gap-3',
                            variant === 'completed' ? 'sm:grid-cols-3' : 'max-w-md',
                        )}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="search" className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <Search className="size-3.5" />
                                بحث
                            </Label>
                            <Input
                                id="search"
                                placeholder="رقم الفاتورة أو الجوال"
                                value={localFilters.search}
                                onChange={(e) => updateFilters({ search: e.target.value }, { debounceSearch: true })}
                                className="bg-background"
                            />
                        </div>
                        {variant === 'completed' && (
                            <>
                                <div className="space-y-1.5">
                                    <Label htmlFor="from_date" className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <CalendarDays className="size-3.5" />
                                        من تاريخ
                                    </Label>
                                    <Input
                                        id="from_date"
                                        type="date"
                                        value={localFilters.from_date ?? ''}
                                        onChange={(e) => updateFilters({ from_date: e.target.value })}
                                        className="bg-background"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="to_date" className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        <CalendarDays className="size-3.5" />
                                        إلى تاريخ
                                    </Label>
                                    <Input
                                        id="to_date"
                                        type="date"
                                        value={localFilters.to_date ?? ''}
                                        onChange={(e) => updateFilters({ to_date: e.target.value })}
                                        className="bg-background"
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="overflow-x-auto p-0">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="raad-table-head">
                                <th className="px-4 py-3 text-right font-medium">رقم الفاتورة</th>
                                <th className="px-4 py-3 text-right font-medium">الأطفال</th>
                                <th className="px-4 py-3 text-right font-medium">الجوال</th>
                                <th className="px-4 py-3 text-center font-medium">المدة</th>
                                <th className="px-4 py-3 text-right font-medium">الدخول</th>
                                <th className="px-4 py-3 text-right font-medium">الخروج</th>
                                {variant === 'active' && <th className="px-4 py-3 text-right font-medium">المتبقي</th>}
                                {variant === 'active' && <th className="px-2 py-3 text-center font-medium">اتصال</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {invoiceRows.length === 0 ? (
                                <tr>
                                    <td colSpan={variant === 'active' ? 8 : 7} className="text-muted-foreground px-4 py-12 text-center">
                                        لا توجد فواتير
                                    </td>
                                </tr>
                            ) : (
                                invoiceRows.map((invoice) => (
                                    <tr
                                        key={invoice.id}
                                        className="hover:bg-muted/40 cursor-pointer border-b transition-colors last:border-0"
                                        onClick={() => openDetail(invoice)}
                                    >
                                        <td className="px-4 py-3 font-semibold">
                                            <span className="text-primary inline-flex items-center gap-1.5">
                                                <Hash className="text-primary/60 size-3.5" />
                                                {invoice.invoice_number}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="secondary" className="gap-1 bg-rose-500/10 text-rose-700 dark:text-rose-300">
                                                <Baby className="size-3" />
                                                {invoice.children_count} طفل
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3" dir="ltr">
                                            <div className="flex items-center justify-end gap-2">
                                                <Badge variant="outline" className="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                                    {invoice.visit_count} زيارة
                                                </Badge>
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1.5 font-medium',
                                                        variant === 'active' &&
                                                            invoice.needs_parent_call &&
                                                            'text-amber-600 dark:text-amber-400',
                                                    )}
                                                >
                                                    <Phone
                                                        className={cn(
                                                            'size-3.5',
                                                            variant === 'active' && invoice.needs_parent_call
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    />
                                                    {invoice.phone_number}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col items-center gap-1">
                                                <SessionBadge name={invoice.session_duration.name} color={invoice.session_duration.color} />
                                                {invoice.extensions.map((ext) => (
                                                    <SessionBadge
                                                        key={ext.id}
                                                        name={ext.session_duration.name}
                                                        color={ext.session_duration.color}
                                                    />
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{invoice.entered_at}</td>
                                        <td className="px-4 py-3">
                                            {formatExitTime(
                                                invoice.expected_exit_at,
                                                invoice.exited_at,
                                                variant === 'active',
                                            )}
                                        </td>
                                        {variant === 'active' && (
                                            <td className="px-4 py-3">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 font-medium',
                                                        invoice.remaining_minutes === null
                                                            ? 'text-muted-foreground'
                                                            : invoice.remaining_minutes <= 10
                                                              ? 'text-destructive'
                                                              : invoice.remaining_minutes <= 20
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-emerald-600 dark:text-emerald-400',
                                                    )}
                                                >
                                                    <Timer className="size-3.5" />
                                                    {formatRemainingMinutes(invoice.remaining_minutes)}
                                                </span>
                                            </td>
                                        )}
                                        {variant === 'active' && (
                                            <td className="px-2 py-3 text-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className={cn(
                                                        'size-8',
                                                        invoice.needs_parent_call
                                                            ? 'text-amber-600 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400'
                                                            : 'text-muted-foreground hover:text-foreground',
                                                    )}
                                                    title={
                                                        invoice.needs_parent_call
                                                            ? 'إزالة طلب الاتصال بولي الأمر'
                                                            : 'طلب اتصال بولي الأمر'
                                                    }
                                                    onClick={(event) => toggleParentCall(event, invoice)}
                                                >
                                                    <PhoneCall className="size-4" />
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                            {invoices.current_page < invoices.last_page && (
                                <WhenVisible
                                    as="tr"
                                    data="invoices"
                                    buffer={400}
                                    params={{
                                        data: { page: invoices.current_page + 1 },
                                        only: ['invoices'],
                                    }}
                                    fallback={
                                        <td colSpan={variant === 'active' ? 8 : 7} className="px-4 py-4">
                                            <Skeleton className="h-12 w-full rounded-lg" />
                                        </td>
                                    }
                                >
                                    <td colSpan={variant === 'active' ? 8 : 7} className="h-1 p-0" aria-hidden="true" />
                                </WhenVisible>
                            )}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>تسجيل فاتورة جديدة</DialogTitle>
                        <DialogDescription>أدخل بيانات العميل واختر مدة اللعب</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="invoice_number">رقم الفاتورة</Label>
                                <Input
                                    id="invoice_number"
                                    value={data.invoice_number}
                                    onChange={(e) => setData('invoice_number', e.target.value)}
                                />
                                {errors.invoice_number && <p className="text-destructive text-sm">{errors.invoice_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="children_count">عدد الأطفال</Label>
                                <Input
                                    id="children_count"
                                    type="number"
                                    min={1}
                                    value={data.children_count}
                                    onChange={(e) => setData('children_count', Number(e.target.value))}
                                />
                                {errors.children_count && <p className="text-destructive text-sm">{errors.children_count}</p>}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="phone_number">رقم الجوال</Label>
                            <Input
                                id="phone_number"
                                dir="ltr"
                                value={data.phone_number}
                                onChange={(e) => setData('phone_number', e.target.value)}
                            />
                            {errors.phone_number && <p className="text-destructive text-sm">{errors.phone_number}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>مدة الجلسة</Label>
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                {sessionDurations.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        boxShadowActive={selectedSessionTime === session.id}
                                        onClick={() => {
                                            setSelectedSessionTime(session.id);
                                            setData('session_duration_id', session.id);
                                        }}
                                    />
                                ))}
                            </div>
                            {errors.session_duration_id && <p className="text-destructive text-sm">{errors.session_duration_id}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={processing}>
                                تسجيل الفاتورة
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <InvoiceDetailDialog
                invoiceId={detailInvoiceId}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                isAdmin={auth.user.is_admin}
                showReceptionActions={variant === 'active'}
                showReactivate={variant === 'completed' && auth.user.is_admin}
                sessionDurations={sessionDurations}
            />
        </div>
    );
}
