import {
    RaadDesktopTable,
    RaadMobileBody,
    RaadMobileCard,
    RaadMobileCardHeader,
    RaadMobileStack,
} from '@/components/raad/raad-responsive-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { PreviousVisitInvoice, PreviousVisitsPayload } from '@/types/domain';
import {
    AlertCircle,
    Baby,
    CalendarDays,
    Gift,
    Hash,
    History,
    LoaderCircle,
    Phone,
    Users,
    Wallet,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

function PreviousVisitsPagination({
    currentPage,
    lastPage,
    perPage,
    total,
    onPageChange,
    disabled,
}: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    onPageChange: (page: number) => void;
    disabled?: boolean;
}) {
    if (lastPage <= 1) {
        return null;
    }

    const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm">
                عرض {from}–{to} من {total}
            </p>
            <div className="flex items-center justify-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                >
                    <ChevronRight className="size-4" />
                    السابق
                </Button>
                <span className="text-muted-foreground min-w-16 text-center text-sm">
                    {currentPage} / {lastPage}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || currentPage >= lastPage}
                    onClick={() => onPageChange(currentPage + 1)}
                >
                    التالي
                    <ChevronLeft className="size-4" />
                </Button>
            </div>
        </div>
    );
}

function visitHighlightClass(
    visit: PreviousVisitInvoice,
    currentInvoiceId?: number | null,
    currentExpiredBonusId?: number | null,
): string {
    if (!visit.is_expired_bonus && visit.id === currentInvoiceId) {
        return 'bg-primary/8 ring-1 ring-inset ring-primary/20';
    }

    if (visit.is_expired_bonus && visit.id === currentExpiredBonusId) {
        return 'bg-rose-500/8 ring-1 ring-inset ring-rose-500/20';
    }

    if (visit.is_expired_bonus) {
        return 'bg-muted/20';
    }

    return 'hover:bg-muted/30';
}

function VisitInvoiceLabel({
    visit,
    className,
    compact,
}: {
    visit: PreviousVisitInvoice;
    className?: string;
    compact?: boolean;
}) {
    const iconClass = compact ? 'size-3 shrink-0' : 'size-3.5 shrink-0';

    const label = visit.invoice_display_label ?? visit.invoice_number;

    if (visit.is_expired_bonus) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-rose-600 dark:text-rose-400',
                    compact ? 'text-xs font-medium' : 'gap-1.5',
                    className,
                )}
            >
                <AlertCircle className={iconClass} />
                {label}
            </span>
        );
    }

    if (visit.is_bonus) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400',
                    compact ? 'text-xs font-medium' : 'gap-1.5',
                    className,
                )}
            >
                <Gift className={iconClass} />
                {label}
            </span>
        );
    }

    if (visit.is_credit) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-sky-600 dark:text-sky-400',
                    compact ? 'text-xs font-medium' : 'gap-1.5',
                    className,
                )}
            >
                <Wallet className={iconClass} />
                {label}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'text-primary inline-flex items-center gap-1',
                compact ? 'text-xs font-medium' : 'gap-1.5',
                className,
            )}
        >
            <Hash className={cn('text-primary/60', iconClass)} />
            {label}
        </span>
    );
}

function SessionBadge({ name, color, compact }: { name: string; color: string; compact?: boolean }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-0 font-medium text-white',
                compact ? 'px-1.5 py-0 text-[11px] leading-5 shadow-none' : 'shadow-sm',
            )}
            style={{
                backgroundColor: color,
                boxShadow: compact ? undefined : `${color}33 0 2px 8px`,
            }}
        >
            {name}
        </Badge>
    );
}

function ChildrenBadge({ count, className, compact }: { count: number; className?: string; compact?: boolean }) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'gap-0.5 bg-rose-500/10 text-rose-700 dark:text-rose-300',
                compact ? 'h-5 px-1.5 text-[11px]' : 'gap-1',
                className,
            )}
        >
            <Baby className={compact ? 'size-2.5' : 'size-3'} />
            {count} طفل
        </Badge>
    );
}

function ExtensionBadges({
    visit,
    className,
    compact,
}: {
    visit: PreviousVisitInvoice;
    className?: string;
    compact?: boolean;
}) {
    const extensions = visit.extensions ?? [];

    if (extensions.length === 0 && visit.extensions_duration_minutes <= 0) {
        return <span className={cn('text-muted-foreground', compact ? 'text-xs' : 'text-sm')}>—</span>;
    }

    return (
        <div className={cn('flex flex-col items-start', compact ? 'gap-0.5' : 'gap-1', className)}>
            {extensions.map((extension, index) => (
                <SessionBadge
                    key={`${visit.id}-ext-${index}`}
                    name={extension.name}
                    color={extension.color}
                    compact={compact}
                />
            ))}
            {visit.extensions_duration_minutes > 0 && extensions.length === 0 && (
                <Badge
                    variant="outline"
                    className={cn(
                        'text-muted-foreground font-medium',
                        compact ? 'h-5 px-1.5 text-[11px]' : 'text-xs',
                    )}
                >
                    تمديد {visit.extensions_duration_minutes} د
                </Badge>
            )}
        </div>
    );
}

function VisitSessionBadges({ visit, className }: { visit: PreviousVisitInvoice; className?: string }) {
    return (
        <div className={cn('flex flex-wrap items-center gap-1.5', className)}>
            <SessionBadge name={visit.original_session.name} color={visit.original_session.color} />
            {(visit.extensions ?? []).map((extension, index) => (
                <SessionBadge key={`${visit.id}-ext-${index}`} name={extension.name} color={extension.color} />
            ))}
            {visit.extensions_duration_minutes > 0 && (visit.extensions ?? []).length === 0 && (
                <Badge variant="outline" className="text-muted-foreground text-xs font-medium">
                    تمديد {visit.extensions_duration_minutes} د
                </Badge>
            )}
        </div>
    );
}

export function PreviousVisitsPanel({
    payload,
    loading,
    currentInvoiceId,
    currentExpiredBonusId,
    onVisitClick,
    onPageChange,
    pageLoading,
}: {
    payload: PreviousVisitsPayload | null;
    loading: boolean;
    currentInvoiceId?: number | null;
    currentExpiredBonusId?: number | null;
    onVisitClick?: (visit: PreviousVisitInvoice) => void;
    onPageChange?: (page: number) => void;
    pageLoading?: boolean;
}) {
    const visits = payload?.invoices.data ?? [];
    const pagination = payload?.invoices;
    const isLoading = loading || pageLoading;

    if (loading && !payload) {
        return (
            <div className="flex justify-center py-16">
                <LoaderCircle className="text-primary size-8 animate-spin" />
            </div>
        );
    }

    if (!payload || visits.length === 0) {
        return (
            <div className="text-muted-foreground flex flex-col items-center gap-2 py-16 text-center text-sm">
                <History className="text-muted-foreground/60 size-8" />
                لا توجد فواتير سابقة
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <Card className="raad-card-surface">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Badge
                            variant="outline"
                            className="gap-1.5 border-sky-500/30 bg-sky-500/10 text-base font-semibold text-sky-700 dark:text-sky-300"
                            dir="ltr"
                        >
                            <Phone className="size-3.5" />
                            {payload.phone_number}
                        </Badge>
                        <div className="flex items-center gap-1.5">
                            <Badge
                                variant="secondary"
                                className="h-7 gap-1 bg-amber-500/10 px-2 text-xs text-amber-700 md:h-auto md:py-1 md:px-2.5 dark:text-amber-300"
                            >
                                <CalendarDays className="size-3 shrink-0" />
                                <span className="hidden md:inline">عدد الزيارات</span>
                                {payload.visit_count}
                            </Badge>
                            <Badge
                                variant="secondary"
                                className="h-7 gap-1 bg-emerald-500/10 px-2 text-xs text-emerald-700 md:h-auto md:py-1 md:px-2.5 dark:text-emerald-300"
                            >
                                <Users className="size-3 shrink-0" />
                                <span className="hidden md:inline">
                                    متوسط الأطفال (آخر {payload.average_children_visit_threshold} زيارات){' '}
                                </span>
                                {payload.average_children}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="raad-card-surface overflow-hidden">
                <CardHeader className="hidden border-b py-2.5 md:block">
                    <CardTitle className="text-sm font-bold">
                        سجل الزيارات ({pagination?.total ?? visits.length})
                    </CardTitle>
                </CardHeader>
                <CardContent className={cn('p-0', isLoading && 'opacity-60')}>
                    <RaadDesktopTable className="text-sm">
                        <thead>
                            <tr className="raad-table-head">
                                <th className="px-4 py-2.5 text-right text-sm font-medium">الفاتورة</th>
                                <th className="px-4 py-2.5 text-right text-sm font-medium">الأطفال</th>
                                <th className="px-4 py-2.5 text-right text-sm font-medium">المدة</th>
                                <th className="px-4 py-2.5 text-right text-sm font-medium">التمديدات</th>
                                <th className="px-4 py-2.5 text-right text-sm font-medium">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visits.map((visit) => (
                                <tr
                                    key={visit.is_expired_bonus ? `expired-${visit.id}` : visit.id}
                                    className={cn(
                                        'border-b transition-colors last:border-0',
                                        visitHighlightClass(visit, currentInvoiceId, currentExpiredBonusId),
                                        onVisitClick && 'cursor-pointer hover:bg-muted/40',
                                    )}
                                    onClick={onVisitClick ? () => onVisitClick(visit) : undefined}
                                >
                                    <td className="px-4 py-2.5">
                                        <VisitInvoiceLabel visit={visit} className="text-sm" />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <ChildrenBadge count={visit.children_count} />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <SessionBadge
                                            name={visit.original_session.name}
                                            color={visit.original_session.color}
                                        />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <ExtensionBadges visit={visit} />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <span
                                            className="text-muted-foreground inline-flex items-center gap-1.5 text-sm"
                                            dir="ltr"
                                        >
                                            <CalendarDays className="size-3.5 shrink-0" />
                                            {visit.visit_date ?? '—'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </RaadDesktopTable>

                    <RaadMobileStack>
                        {visits.map((visit) => (
                            <RaadMobileCard
                                key={visit.is_expired_bonus ? `expired-${visit.id}` : visit.id}
                                className={cn(
                                    visit.is_expired_bonus && 'bg-muted/20',
                                    !visit.is_expired_bonus && visit.id === currentInvoiceId && 'ring-1 ring-primary/20',
                                    visit.is_expired_bonus &&
                                        visit.id === currentExpiredBonusId &&
                                        'ring-1 ring-rose-500/20',
                                    onVisitClick && 'cursor-pointer',
                                )}
                                onClick={onVisitClick ? () => onVisitClick(visit) : undefined}
                            >
                                <RaadMobileCardHeader
                                    title={<VisitInvoiceLabel visit={visit} className="text-sm font-semibold" />}
                                    trailing={
                                        <span
                                            className="text-muted-foreground inline-flex items-center gap-1 text-xs"
                                            dir="ltr"
                                        >
                                            <CalendarDays className="size-3 shrink-0" />
                                            {visit.visit_date ?? '—'}
                                        </span>
                                    }
                                />
                                <RaadMobileBody>
                                    <div className="flex flex-wrap items-center justify-center gap-3">
                                        <ChildrenBadge count={visit.children_count} />
                                        <VisitSessionBadges visit={visit} />
                                    </div>
                                </RaadMobileBody>
                            </RaadMobileCard>
                        ))}
                    </RaadMobileStack>
                    {pagination && onPageChange ? (
                        <PreviousVisitsPagination
                            currentPage={pagination.current_page}
                            lastPage={pagination.last_page}
                            perPage={pagination.per_page}
                            total={pagination.total}
                            onPageChange={onPageChange}
                            disabled={isLoading}
                        />
                    ) : null}
                </CardContent>
            </Card>
        </div>
    );
}
