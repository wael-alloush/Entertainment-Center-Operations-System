import { InvoiceCreatePendingRecords } from '@/components/raad/invoice-create-pending-records';
import { InvoiceCreatePreviousVisits } from '@/components/raad/invoice-create-previous-visits';
import { RaadDateInput } from '@/components/raad/raad-date-input';
import { InvoiceDetailDialog } from '@/components/raad/invoice-detail-dialog';
import { SessionCard } from '@/components/raad/session-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatExitTime,
    formatRemainingMinutes,
    remainingTimeBadgeClass,
    remainingTimeRowClass,
    remainingTimeTextClass,
} from '@/lib/format-time';
import { flushPrefetchedListPages } from '@/lib/inertia-list-pages';
import { cn } from '@/lib/utils';
import { Auth } from '@/types';
import {
    RaadDesktopTable,
    RaadMobileBody,
    RaadMobileCard,
    RaadMobileCardHeader,
    RaadMobileGrid,
    RaadMobileStack,
    RaadMobileStat,
    RaadTableEmpty,
} from '@/components/raad/raad-responsive-table';
import { RaadPagination } from '@/components/raad/raad-pagination';
import { useActiveInvoiceSync } from '@/hooks/use-active-invoice-sync';
import { useLiveRemainingMinutes } from '@/hooks/use-live-remaining-minutes';
import { ActiveInvoiceChanges, Invoice, InvoiceFilters, InvoiceSummary, PaginatedInvoices, SessionDuration } from '@/types/domain';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Baby,
    Gamepad2,
    Gift,
    Hash,
    History,
    Phone,
    PlayCircle,
    Plus,
    Search,
    Timer,
    Wallet,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

const defaultFilters: InvoiceFilters = {
    search: '',
    from_date: '',
    to_date: '',
};

function filtersToParams(
    filters: InvoiceFilters,
    options?: { includeCompletedDates?: boolean },
): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.search) {
        params.search = filters.search;
    }

    if (options?.includeCompletedDates) {
        params.from_date = filters.from_date ?? '';
        params.to_date = filters.to_date ?? '';
    }

    return params;
}

function hasActiveFilters(filters: InvoiceFilters, variant: 'active' | 'completed', canFilterCompletedByDate: boolean): boolean {
    if (filters.search) {
        return true;
    }

    if (variant === 'completed' && canFilterCompletedByDate) {
        return Boolean(filters.from_date || filters.to_date);
    }

    return false;
}

interface InvoicesTableProps {
    invoices: PaginatedInvoices;
    sessionDurations: SessionDuration[];
    summary: InvoiceSummary;
    filters: InvoiceFilters;
    variant: 'active' | 'completed';
    routeName: 'invoices.active' | 'invoices.completed';
    canFilterCompletedByDate?: boolean;
    nextInvoiceNumber?: string;
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

function InvoiceTitle({ invoice }: { invoice: Invoice }) {
    if (invoice.is_expired_bonus) {
        return (
            <span className="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                <AlertCircle className="size-3.5" />
                {invoice.invoice_display_label}
            </span>
        );
    }

    if (invoice.is_bonus) {
        return (
            <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <Gift className="size-3.5" />
                {invoice.invoice_display_label}
            </span>
        );
    }

    if (invoice.is_credit) {
        return (
            <span className="inline-flex items-center gap-1.5 text-sky-600 dark:text-sky-400">
                <Wallet className="size-3.5" />
                {invoice.invoice_display_label}
            </span>
        );
    }

    return (
        <span className="text-primary inline-flex items-center gap-1.5">
            <Hash className="text-primary/60 size-3.5" />
            {invoice.invoice_number}
        </span>
    );
}

function suggestNextInvoiceNumberFromSubmitted(submitted: string, fallback: string): string {
    if (/^\d+$/.test(submitted)) {
        return String(parseInt(submitted, 10) + 1);
    }

    return fallback;
}

export function InvoicesTable({
    invoices,
    sessionDurations,
    summary,
    filters,
    variant,
    routeName,
    canFilterCompletedByDate = false,
    nextInvoiceNumber = '',
}: InvoicesTableProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [invoiceRows, setInvoiceRows] = useState(invoices.data);
    const [displaySummary, setDisplaySummary] = useState(summary);
    const [createOpen, setCreateOpen] = useState(false);
    const [detailInvoiceId, setDetailInvoiceId] = useState<number | null>(null);
    const [detailExpiredBonusId, setDetailExpiredBonusId] = useState<number | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [selectedSessionTime, setSelectedSessionTime] = useState<number | null>(null);
    const [localFilters, setLocalFilters] = useState(filters);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        invoice_number: nextInvoiceNumber,
        phone_number: '',
        children_count: '' as number | '',
        session_duration_id: 0,
    });

    useEffect(() => {
        if (!createOpen) {
            setData('invoice_number', nextInvoiceNumber);
        }
    }, [createOpen, nextInvoiceNumber, setData]);

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

    useActiveInvoiceSync(variant === 'active' && !hasActiveFilters(localFilters, 'active', false), applyInvoiceChanges, syncResetKey);

    const displayInvoiceRows = useLiveRemainingMinutes(invoiceRows, variant === 'active');

    useEffect(() => {
        return () => {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }
        };
    }, []);

    const pushFilters = useCallback(
        (nextFilters: InvoiceFilters) => {
            router.get(
                route(routeName),
                filtersToParams(nextFilters, {
                    includeCompletedDates: variant === 'completed',
                }),
                {
                    preserveState: true,
                    replace: true,
                    reset: ['invoices'],
                },
            );
        },
        [routeName, variant],
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

        if (variant === 'completed' && canFilterCompletedByDate) {
            const clearedFilters = { search: '', from_date: '', to_date: '' };
            setLocalFilters(clearedFilters);
            router.get(route(routeName), { from_date: '', to_date: '' }, { preserveState: true, replace: true, reset: ['invoices'] });

            return;
        }

        if (variant === 'completed') {
            const clearedFilters = { ...localFilters, search: '' };
            setLocalFilters(clearedFilters);
            router.get(route(routeName), {}, { preserveState: true, replace: true, reset: ['invoices'] });

            return;
        }

        setLocalFilters(defaultFilters);
        router.get(route(routeName), {}, { preserveState: true, replace: true, reset: ['invoices'] });
    };

    const closeCreateDialog = () => {
        setData({
            invoice_number: nextInvoiceNumber,
            phone_number: '',
            children_count: '' as number | '',
            session_duration_id: 0,
        });
        setCreateOpen(false);
        setSelectedSessionTime(null);
    };

    const openCreateDialog = () => {
        setData('invoice_number', nextInvoiceNumber);
        setCreateOpen(true);
    };

    const handleCreateOpenChange = (open: boolean) => {
        if (open) {
            setData('invoice_number', nextInvoiceNumber);
            setCreateOpen(true);

            return;
        }

        closeCreateDialog();
    };

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        post(route('invoices.store'), {
            preserveScroll: true,
            reset: ['invoices', 'summary', 'nextInvoiceNumber'],
            onSuccess: () => {
                flushPrefetchedListPages();
                setData({
                    invoice_number: suggestNextInvoiceNumberFromSubmitted(data.invoice_number, nextInvoiceNumber),
                    phone_number: '',
                    children_count: '' as number | '',
                    session_duration_id: 0,
                });
                setCreateOpen(false);
                setSelectedSessionTime(null);
            },
        });
    };

    const openDetail = (invoice: Invoice) => {
        if (invoice.is_expired_bonus) {
            setDetailInvoiceId(null);
            setDetailExpiredBonusId(invoice.id);
        } else {
            setDetailExpiredBonusId(null);
            setDetailInvoiceId(invoice.id);
        }

        setDetailOpen(true);
    };

    const rowKey = (invoice: Invoice) => (invoice.is_expired_bonus ? `expired-${invoice.id}` : String(invoice.id));

    const activeTimeHighlight = (invoice: Invoice) =>
        variant === 'active' && !invoice.is_expired_bonus ? remainingTimeRowClass(invoice.remaining_minutes) : '';

    const title = variant === 'active' ? 'الفواتير النشطة' : 'الفواتير السابقة';

    return (
        <div className="space-y-4">
            <header className="space-y-3">
                {variant === 'active' ? (
                    <>
                        <div className="space-y-3 md:hidden">
                            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                <h1 className="raad-page-title flex items-center gap-2">
                                    <span className="raad-icon-chip bg-emerald-500/12 text-emerald-600 dark:text-emerald-400">
                                        <PlayCircle className="size-5" strokeWidth={2.25} />
                                    </span>
                                    {title}
                                </h1>

                                <div className="flex shrink-0 items-center gap-1.5">
                                    <Badge variant="secondary" className="h-7 gap-1 bg-primary/10 px-2 text-xs text-primary">
                                        <Gamepad2 className="size-3" />
                                        {displaySummary.invoice_count}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        className="h-7 gap-1 bg-rose-500/10 px-2 text-xs text-rose-700 dark:text-rose-300"
                                    >
                                        <Baby className="size-3" />
                                        {displaySummary.children_count}
                                    </Badge>
                                </div>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div className="relative min-w-0 flex-1">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="search"
                                        placeholder="رقم الفاتورة أو الجوال"
                                        value={localFilters.search}
                                        onChange={(e) => updateFilters({ search: e.target.value }, { debounceSearch: true })}
                                        className="bg-background h-10 pr-9"
                                    />
                                </div>

                                {hasActiveFilters(localFilters, variant, canFilterCompletedByDate) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="text-muted-foreground h-10 shrink-0"
                                        onClick={clearFilters}
                                    >
                                        <X className="size-4" />
                                        مسح الفلاتر
                                    </Button>
                                )}

                                <Button
                                    type="button"
                                    className="min-h-11 w-full shrink-0 shadow-md shadow-primary/20 sm:w-auto"
                                    onClick={openCreateDialog}
                                >
                                    <Plus className="size-4" />
                                    فاتورة جديدة
                                </Button>
                            </div>
                        </div>

                        <div className="hidden md:flex md:items-center md:gap-4">
                            <div className="flex shrink-0 items-center gap-2">
                                <h1 className="raad-page-title flex items-center gap-2">
                                    <span className="raad-icon-chip bg-emerald-500/12 text-emerald-600 dark:text-emerald-400">
                                        <PlayCircle className="size-5" strokeWidth={2.25} />
                                    </span>
                                    {title}
                                </h1>

                                <div className="flex shrink-0 items-center gap-1.5">
                                    <Badge variant="secondary" className="h-7 gap-1 bg-primary/10 px-2 text-xs text-primary">
                                        <Gamepad2 className="size-3" />
                                        {displaySummary.invoice_count}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        className="h-7 gap-1 bg-rose-500/10 px-2 text-xs text-rose-700 dark:text-rose-300"
                                    >
                                        <Baby className="size-3" />
                                        {displaySummary.children_count}
                                    </Badge>
                                </div>
                            </div>

                            <div className="flex min-w-0 flex-1 justify-center px-2">
                                <div className="relative w-full max-w-[15rem]">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="search-desktop"
                                        placeholder="رقم الجوال"
                                        value={localFilters.search}
                                        onChange={(e) => updateFilters({ search: e.target.value }, { debounceSearch: true })}
                                        className="bg-background h-10 pr-9"
                                        inputMode="numeric"
                                    />
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {hasActiveFilters(localFilters, variant, canFilterCompletedByDate) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="text-muted-foreground h-10 shrink-0"
                                        onClick={clearFilters}
                                    >
                                        <X className="size-4" />
                                        مسح الفلاتر
                                    </Button>
                                )}

                                <Button
                                    type="button"
                                    className="min-h-10 shrink-0 shadow-md shadow-primary/20"
                                    onClick={openCreateDialog}
                                >
                                    <Plus className="size-4" />
                                    فاتورة جديدة
                                </Button>
                            </div>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="space-y-3 md:hidden">
                            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                <h1 className="raad-page-title flex items-center gap-2">
                                    <span className="raad-icon-chip bg-violet-500/12 text-violet-600 dark:text-violet-400">
                                        <History className="size-5" strokeWidth={2.25} />
                                    </span>
                                    {title}
                                </h1>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div className="relative min-w-0 flex-1">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="search"
                                        placeholder="رقم الفاتورة أو الجوال"
                                        value={localFilters.search}
                                        onChange={(e) => updateFilters({ search: e.target.value }, { debounceSearch: true })}
                                        className="bg-background h-10 pr-9"
                                    />
                                </div>
                                <div className="raad-filter-dates w-full sm:w-auto">
                                    <div className="flex items-center gap-1.5">
                                        <Label htmlFor="from_date" className="text-muted-foreground shrink-0 text-xs font-medium">
                                            من
                                        </Label>
                                        <RaadDateInput
                                            id="from_date"
                                            aria-label="من تاريخ"
                                            value={localFilters.from_date ?? ''}
                                            onChange={(from_date) => updateFilters({ from_date })}
                                            className="bg-background w-full min-[400px]:w-auto"
                                        />
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Label htmlFor="to_date" className="text-muted-foreground shrink-0 text-xs font-medium">
                                            إلى
                                        </Label>
                                        <RaadDateInput
                                            id="to_date"
                                            aria-label="إلى تاريخ"
                                            value={localFilters.to_date ?? ''}
                                            onChange={(to_date) => updateFilters({ to_date })}
                                            className="bg-background w-full min-[400px]:w-auto"
                                        />
                                    </div>
                                </div>
                                {hasActiveFilters(localFilters, variant, canFilterCompletedByDate) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="text-muted-foreground h-10 shrink-0"
                                        onClick={clearFilters}
                                    >
                                        <X className="size-4" />
                                        مسح الفلاتر
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="hidden md:flex md:items-center md:gap-4">
                            <div className="flex shrink-0 items-center">
                                <h1 className="raad-page-title flex items-center gap-2">
                                    <span className="raad-icon-chip bg-violet-500/12 text-violet-600 dark:text-violet-400">
                                        <History className="size-5" strokeWidth={2.25} />
                                    </span>
                                    {title}
                                </h1>
                            </div>

                            <div className="flex min-w-0 flex-1 flex-wrap items-center justify-center gap-2 px-2">
                                <div className="relative w-full max-w-[15rem]">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="search-desktop"
                                        placeholder="رقم الفاتورة أو الجوال"
                                        value={localFilters.search}
                                        onChange={(e) => updateFilters({ search: e.target.value }, { debounceSearch: true })}
                                        className="bg-background h-10 pr-9"
                                    />
                                </div>
                                <div className="raad-filter-dates shrink-0">
                                    <div className="flex items-center gap-1.5">
                                        <Label htmlFor="from_date_desktop" className="text-muted-foreground shrink-0 text-xs font-medium">
                                            من
                                        </Label>
                                        <RaadDateInput
                                            id="from_date_desktop"
                                            aria-label="من تاريخ"
                                            value={localFilters.from_date ?? ''}
                                            onChange={(from_date) => updateFilters({ from_date })}
                                            className="bg-background w-auto"
                                        />
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Label htmlFor="to_date_desktop" className="text-muted-foreground shrink-0 text-xs font-medium">
                                            إلى
                                        </Label>
                                        <RaadDateInput
                                            id="to_date_desktop"
                                            aria-label="إلى تاريخ"
                                            value={localFilters.to_date ?? ''}
                                            onChange={(to_date) => updateFilters({ to_date })}
                                            className="bg-background w-auto"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {hasActiveFilters(localFilters, variant, canFilterCompletedByDate) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="text-muted-foreground h-10 shrink-0"
                                        onClick={clearFilters}
                                    >
                                        <X className="size-4" />
                                        مسح الفلاتر
                                    </Button>
                                )}
                            </div>
                        </div>
                    </>
                )}
            </header>

            <Card className="raad-card-surface">
                <CardContent className="p-0">
                    <RaadDesktopTable>
                        <thead>
                            <tr className="raad-table-head">
                                <th className="px-4 py-3 text-right font-medium">رقم الفاتورة</th>
                                <th className="px-4 py-3 text-right font-medium">الأطفال</th>
                                <th className="px-4 py-3 text-right font-medium">الجوال</th>
                                <th className="px-4 py-3 text-center font-medium">المدة</th>
                                <th className="px-4 py-3 text-right font-medium">الدخول</th>
                                <th className="px-4 py-3 text-right font-medium">الخروج</th>
                                {variant === 'active' && <th className="px-4 py-3 text-right font-medium">المتبقي</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {displayInvoiceRows.length === 0 ? (
                                <tr>
                                    <td colSpan={variant === 'active' ? 7 : 6} className="text-muted-foreground px-4 py-12 text-center">
                                        لا توجد فواتير
                                    </td>
                                </tr>
                            ) : (
                                displayInvoiceRows.map((invoice) => (
                                    <tr
                                        key={rowKey(invoice)}
                                        className={cn(
                                            'border-b transition-colors last:border-0 cursor-pointer',
                                            invoice.is_expired_bonus
                                                ? 'bg-muted/20 hover:bg-muted/30'
                                                : activeTimeHighlight(invoice) || 'hover:bg-muted/40',
                                        )}
                                        onClick={() => openDetail(invoice)}
                                    >
                                        <td className="max-w-[8rem] px-4 py-3 font-semibold break-words sm:max-w-none">
                                            <InvoiceTitle invoice={invoice} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="secondary" className="gap-1 bg-rose-500/10 text-rose-700 dark:text-rose-300">
                                                <Baby className="size-3" />
                                                {invoice.children_count} طفل
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center justify-center gap-2" dir="ltr">
                                                <span className="inline-flex items-center gap-1.5 font-medium">
                                                    <Phone className="text-muted-foreground size-3.5" />
                                                    {invoice.phone_number}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'inline-grid size-7 shrink-0 place-items-center rounded-full border text-xs font-semibold tabular-nums',
                                                        invoice.is_bonus_eligible
                                                            ? 'border-amber-500 bg-amber-500 text-white dark:border-amber-400 dark:bg-amber-400 dark:text-amber-950'
                                                            : 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
                                                    )}
                                                >
                                                    <span className="block leading-none">{invoice.visit_count}</span>
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
                                        <td className="px-4 py-3">{invoice.is_expired_bonus ? '—' : invoice.entered_at}</td>
                                        <td className="px-4 py-3">
                                            {invoice.is_expired_bonus
                                                ? invoice.expired_at
                                                : formatExitTime(
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
                                                        remainingTimeTextClass(invoice.remaining_minutes),
                                                    )}
                                                >
                                                    <Timer className="size-3.5" />
                                                    {formatRemainingMinutes(invoice.remaining_minutes)}
                                                </span>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </RaadDesktopTable>

                    {displayInvoiceRows.length === 0 ? (
                        <RaadTableEmpty className="md:hidden">لا توجد فواتير</RaadTableEmpty>
                    ) : (
                        <RaadMobileStack>
                            {displayInvoiceRows.map((invoice) => (
                                <RaadMobileCard
                                    key={rowKey(invoice)}
                                    className={cn(
                                        invoice.is_expired_bonus
                                            ? 'bg-muted/20'
                                            : activeTimeHighlight(invoice),
                                    )}
                                    onClick={() => openDetail(invoice)}
                                >
                                    <RaadMobileCardHeader
                                        title={<InvoiceTitle invoice={invoice} />}
                                        middle={
                                            <span className="inline-flex items-center justify-center gap-1.5" dir="ltr">
                                                <span className="inline-flex items-center gap-1 font-medium">
                                                    <Phone className="text-muted-foreground size-3.5 shrink-0" />
                                                    {invoice.phone_number}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'inline-grid size-7 shrink-0 place-items-center rounded-full border text-xs font-semibold tabular-nums',
                                                        invoice.is_bonus_eligible
                                                            ? 'border-amber-500 bg-amber-500 text-white'
                                                            : 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
                                                    )}
                                                >
                                                    {invoice.visit_count}
                                                </span>
                                            </span>
                                        }
                                        trailing={
                                            variant === 'active' && !invoice.is_expired_bonus ? (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold',
                                                        remainingTimeBadgeClass(invoice.remaining_minutes),
                                                    )}
                                                >
                                                    <Timer className="size-3" />
                                                    {formatRemainingMinutes(invoice.remaining_minutes)}
                                                </span>
                                            ) : null
                                        }
                                    />
                                    <RaadMobileBody>
                                        <div className="flex flex-wrap items-center justify-center gap-6">
                                            <Badge variant="secondary" className="gap-1 bg-rose-500/10 text-rose-700 dark:text-rose-300">
                                                <Baby className="size-3" />
                                                {invoice.children_count} طفل
                                            </Badge>
                                            <SessionBadge name={invoice.session_duration.name} color={invoice.session_duration.color} />
                                            {invoice.extensions.map((ext) => (
                                                <SessionBadge
                                                    key={ext.id}
                                                    name={ext.session_duration.name}
                                                    color={ext.session_duration.color}
                                                />
                                            ))}
                                        </div>
                                        <RaadMobileGrid>
                                            <RaadMobileStat label="الدخول">
                                                {invoice.is_expired_bonus ? '—' : invoice.entered_at}
                                            </RaadMobileStat>
                                            <RaadMobileStat label={invoice.is_expired_bonus ? 'انتهت' : 'الخروج'}>
                                                {invoice.is_expired_bonus
                                                    ? invoice.expired_at
                                                    : formatExitTime(
                                                          invoice.expected_exit_at,
                                                          invoice.exited_at,
                                                          variant === 'active',
                                                      )}
                                            </RaadMobileStat>
                                        </RaadMobileGrid>
                                    </RaadMobileBody>
                                </RaadMobileCard>
                            ))}
                        </RaadMobileStack>
                    )}
                </CardContent>
                <RaadPagination
                    links={invoices.links}
                    currentPage={invoices.current_page}
                    lastPage={invoices.last_page}
                    perPage={invoices.per_page}
                    total={invoices.total}
                    only={['invoices']}
                    queryParams={filtersToParams(localFilters, {
                        includeCompletedDates: variant === 'completed' && canFilterCompletedByDate,
                    })}
                />
            </Card>

            <Dialog open={createOpen} onOpenChange={handleCreateOpenChange}>
                <DialogContent
                    className="raad-dialog-surface-lg flex max-h-[min(calc(100dvh-2rem),90vh)] flex-col gap-0 overflow-hidden p-0 [&>button.absolute]:hidden"
                    dir="rtl"
                >
                    <div className="flex-1 overflow-y-auto px-4 pt-4 pb-3">
                        <DialogHeader className="space-y-0 text-center md:text-right">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1 space-y-1 md:flex-none md:text-right">
                                    <DialogTitle>تسجيل فاتورة جديدة</DialogTitle>
                                    <DialogDescription className="text-xs sm:text-sm">
                                        أدخل بيانات العميل واختر مدة اللعب
                                    </DialogDescription>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5">
                                    {auth.user.is_admin && (
                                        <InvoiceCreatePreviousVisits
                                            phoneNumber={data.phone_number}
                                            sessionDurations={sessionDurations}
                                            showReceptionActions={variant === 'active'}
                                            showReactivate={variant === 'completed' && auth.user.is_admin}
                                            showDeleteCompleted={variant === 'completed' && auth.user.is_admin}
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground size-9"
                                        onClick={closeCreateDialog}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </DialogHeader>

                        <form id="create-invoice-form" onSubmit={submitCreate} className="mt-4 space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="invoice_number">رقم الفاتورة</Label>
                                    <Input
                                        id="invoice_number"
                                        className="h-10"
                                        value={data.invoice_number}
                                        onChange={(e) => setData('invoice_number', e.target.value)}
                                    />
                                    {errors.invoice_number && (
                                        <p className="text-destructive text-xs">{errors.invoice_number}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="children_count">عدد الأطفال</Label>
                                    <Input
                                        id="children_count"
                                        type="number"
                                        min={1}
                                        className="h-10"
                                        value={data.children_count}
                                        placeholder="عدد الأطفال"
                                        required
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            setData('children_count', value === '' ? '' : Number(value));
                                        }}
                                    />
                                    {errors.children_count && (
                                        <p className="text-destructive text-xs">{errors.children_count}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone_number">رقم الجوال</Label>
                                <Input
                                    id="phone_number"
                                    dir="ltr"
                                    inputMode="tel"
                                    placeholder="05xxxxxxxx"
                                    className="h-10"
                                    value={data.phone_number}
                                    onChange={(e) => setData('phone_number', e.target.value)}
                                />
                                {errors.phone_number && <p className="text-destructive text-sm">{errors.phone_number}</p>}
                            </div>

                            {auth.user.is_admin && (
                                <InvoiceCreatePendingRecords phoneNumber={data.phone_number} onActivated={closeCreateDialog} />
                            )}

                            <div className="space-y-2">
                                <Label>مدة الجلسة</Label>
                                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                                    {sessionDurations.map((session) => (
                                        <SessionCard
                                            key={session.id}
                                            session={session}
                                            compact
                                            boxShadowActive={selectedSessionTime === session.id}
                                            onClick={() => {
                                                setSelectedSessionTime(session.id);
                                                setData('session_duration_id', session.id);
                                            }}
                                        />
                                    ))}
                                </div>
                                {errors.session_duration_id && (
                                    <p className="text-destructive text-sm">{errors.session_duration_id}</p>
                                )}
                            </div>
                        </form>
                    </div>

                    <div className="shrink-0 border-t bg-background p-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-start">
                            <Button
                                type="submit"
                                form="create-invoice-form"
                                disabled={processing}
                                className="min-h-11 w-full sm:w-auto"
                            >
                                تسجيل الفاتورة
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 w-full sm:w-auto"
                                onClick={closeCreateDialog}
                            >
                                إلغاء
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <InvoiceDetailDialog
                invoiceId={detailInvoiceId}
                expiredBonusId={detailExpiredBonusId}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                isAdmin={auth.user.is_admin}
                showReceptionActions={variant === 'active'}
                showReactivate={variant === 'completed' && auth.user.is_admin}
                showDeleteCompleted={variant === 'completed' && auth.user.is_admin}
                sessionDurations={sessionDurations}
            />
        </div>
    );
}
