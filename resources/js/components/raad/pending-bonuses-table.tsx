import { useConfirm } from '@/components/raad/confirm-dialog';
import { EligibleBonusesDialog } from '@/components/raad/eligible-bonuses-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    RaadDesktopTable,
    RaadMobileActions,
    RaadMobileBody,
    RaadMobileCard,
    RaadMobileCardHeader,
    RaadMobileChips,
    RaadMobileGrid,
    RaadMobileStack,
    RaadMobileStat,
    RaadTableEmpty,
} from '@/components/raad/raad-responsive-table';
import { RaadPagination } from '@/components/raad/raad-pagination';
import { flushPrefetchedListPages } from '@/lib/inertia-list-pages';
import { pendingBonusWhatsappUrl } from '@/lib/whatsapp';
import { cn } from '@/lib/utils';
import { InvoiceFilters, PaginatedPendingBonuses, PendingBonus, SessionDuration } from '@/types/domain';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Baby, CalendarDays, Gift, LoaderCircle, MessageCircle, Phone, Search, Timer, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface PendingBonusesTableProps {
    bonuses: PaginatedPendingBonuses;
    filters: InvoiceFilters;
    validityDays: number;
}

function SessionDurationBadge({ session }: { session: SessionDuration }) {
    return (
        <Badge
            variant="outline"
            className="gap-1.5 border-violet-500/30 bg-violet-500/10 font-medium text-violet-700 dark:text-violet-300"
        >
            <Timer className="size-3.5" />
            {session.name}
        </Badge>
    );
}

export function PendingBonusesTable({ bonuses, filters, validityDays }: PendingBonusesTableProps) {
    const confirm = useConfirm();
    const { auth, eligibleBonusWithoutPendingCount } = usePage<SharedData>().props;
    const [localFilters, setLocalFilters] = useState(filters);
    const [activatingId, setActivatingId] = useState<number | null>(null);
    const [eligibleDialogOpen, setEligibleDialogOpen] = useState(false);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    useEffect(() => {
        return () => {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }
        };
    }, []);

    const pushFilters = useCallback((nextFilters: InvoiceFilters) => {
        const params: Record<string, string> = {};

        if (nextFilters.search) {
            params.search = nextFilters.search;
        }

        router.get(route('invoices.bonuses'), params, {
            preserveState: true,
            replace: true,
            reset: ['bonuses'],
        });
    }, []);

    const updateSearch = (search: string) => {
        const nextFilters = { ...localFilters, search };
        setLocalFilters(nextFilters);

        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        searchDebounceRef.current = setTimeout(() => pushFilters(nextFilters), 350);
    };

    const clearFilters = () => {
        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        setLocalFilters({ search: '' });
        router.get(route('invoices.bonuses'), {}, { preserveState: true, replace: true, reset: ['bonuses'] });
    };

    const activateBonus = async (bonus: PendingBonus) => {
        const confirmed = await confirm({
            title: 'تفعيل المكافأة',
            description: `تفعيل المكافأة لرقم ${bonus.phone_number}؟ سيتم إنشاء فاتورة نشطة.`,
            confirmLabel: 'تفعيل',
        });

        if (!confirmed) {
            return;
        }

        setActivatingId(bonus.id);
        router.post(route('customer-bonuses.activate', bonus.id), {}, {
            preserveScroll: true,
            reset: ['bonuses', 'invoices', 'summary', 'pendingBonusCount', 'eligibleBonusWithoutPendingCount'],
            onSuccess: flushPrefetchedListPages,
            onFinish: () => setActivatingId(null),
        });
    };

    return (
        <div className="space-y-4">
            <Card className="raad-card-surface">
                <CardHeader className="space-y-4 border-b pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="text-base font-bold sm:text-lg">المكافآت غير المفعلة</h1>
                        <div className="flex flex-wrap items-center gap-2">
                            {auth.user?.is_admin && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-2 border-violet-500/30 text-violet-700 dark:text-violet-300"
                                    onClick={() => setEligibleDialogOpen(true)}
                                >
                                    <Gift className="size-4" />
                                    أرقام مؤهلة بدون مكافأة
                                    {eligibleBonusWithoutPendingCount > 0 && (
                                        <Badge className="bg-violet-500/15 text-violet-700 dark:text-violet-300">
                                            {eligibleBonusWithoutPendingCount}
                                        </Badge>
                                    )}
                                </Button>
                            )}
                            {localFilters.search && (
                                <Button type="button" variant="ghost" size="sm" className="text-muted-foreground" onClick={clearFilters}>
                                    <X className="size-4" />
                                    مسح البحث
                                </Button>
                            )}
                        </div>
                    </div>
                    <div className="mx-auto max-w-md">
                        <div className="relative">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                            <Input
                                placeholder="ابحث برقم الجوال..."
                                value={localFilters.search}
                                onChange={(event) => updateSearch(event.target.value)}
                                className="bg-background pr-10"
                                dir="ltr"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <RaadDesktopTable>
                        <thead>
                            <tr className="raad-table-head">
                                <th className="px-4 py-3 text-right font-medium">رقم الجوال</th>
                                <th className="px-4 py-3 text-right font-medium">عدد الأطفال</th>
                                <th className="px-4 py-3 text-right font-medium">مدة الجلسة</th>
                                <th className="px-4 py-3 text-right font-medium">تاريخ الإضافة</th>
                                <th className="px-4 py-3 text-right font-medium">تنتهي في</th>
                                <th className="px-4 py-3 text-center font-medium">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bonuses.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        لا توجد مكافآت غير مفعلة
                                    </td>
                                </tr>
                            ) : (
                                bonuses.data.map((bonus) => (
                                    <tr key={bonus.id} className="border-b transition-colors last:border-0 hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-sky-500/30 bg-sky-500/10 font-medium text-sky-700 dark:text-sky-300"
                                                dir="ltr"
                                            >
                                                <Phone className="size-3.5" />
                                                {bonus.phone_number}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                <Baby className="size-3.5" />
                                                {bonus.children_count} طفل
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <SessionDurationBadge session={bonus.session_duration} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-muted-foreground inline-flex items-center gap-1.5" dir="ltr">
                                                <CalendarDays className="size-3.5" />
                                                {bonus.entered_at}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-muted-foreground inline-flex items-center gap-1.5" dir="ltr">
                                                <CalendarDays className="size-3.5" />
                                                {bonus.expires_at ?? '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    type="button"
                                                    className="min-h-11 bg-emerald-600 hover:bg-emerald-700"
                                                    disabled={activatingId === bonus.id}
                                                    onClick={() => activateBonus(bonus)}
                                                >
                                                    {activatingId === bonus.id ? (
                                                        <LoaderCircle className="size-4 animate-spin" />
                                                    ) : (
                                                        <Gift className="size-4" />
                                                    )}
                                                    تفعيل
                                                </Button>
                                                <a
                                                    href={pendingBonusWhatsappUrl(bonus, validityDays)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    title="إرسال رسالة واتساب للعميل"
                                                    className={cn(
                                                        'inline-flex size-9 items-center justify-center rounded-full',
                                                        'bg-emerald-500 text-white transition-colors hover:bg-emerald-600',
                                                    )}
                                                >
                                                    <MessageCircle className="size-4" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </RaadDesktopTable>

                    {bonuses.data.length === 0 ? (
                        <RaadTableEmpty className="md:hidden">لا توجد مكافآت غير مفعلة</RaadTableEmpty>
                    ) : (
                        <RaadMobileStack>
                            {bonuses.data.map((bonus) => (
                                <RaadMobileCard key={bonus.id}>
                                    <RaadMobileCardHeader
                                        title={
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-sky-500/30 bg-sky-500/10 text-base font-semibold text-sky-700 dark:text-sky-300"
                                                dir="ltr"
                                            >
                                                <Phone className="size-3.5" />
                                                {bonus.phone_number}
                                            </Badge>
                                        }
                                    />
                                    <RaadMobileBody>
                                        <RaadMobileChips className="justify-center gap-4">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                <Baby className="size-3.5" />
                                                {bonus.children_count} طفل
                                            </Badge>
                                            <SessionDurationBadge session={bonus.session_duration} />
                                        </RaadMobileChips>
                                        <RaadMobileGrid>
                                            <RaadMobileStat label="أُضيفت" dir="ltr">
                                                {bonus.entered_at}
                                            </RaadMobileStat>
                                            <RaadMobileStat label="تنتهي في" dir="ltr">
                                                {bonus.expires_at ?? '—'}
                                            </RaadMobileStat>
                                        </RaadMobileGrid>
                                    </RaadMobileBody>
                                    <RaadMobileActions className="!flex flex-row items-center gap-2 [&_a]:!size-11 [&_a]:shrink-0 [&_button]:min-h-11 [&_button]:flex-1">
                                        <Button
                                            type="button"
                                            className="bg-emerald-600 hover:bg-emerald-700"
                                            disabled={activatingId === bonus.id}
                                            onClick={() => activateBonus(bonus)}
                                        >
                                            {activatingId === bonus.id ? (
                                                <LoaderCircle className="size-4 animate-spin" />
                                            ) : (
                                                <Gift className="size-4" />
                                            )}
                                            تفعيل
                                        </Button>
                                        <a
                                            href={pendingBonusWhatsappUrl(bonus, validityDays)}
                                            target="_blank"
                                            rel="noreferrer"
                                            title="إرسال رسالة واتساب للعميل"
                                            className={cn(
                                                'inline-flex size-11 items-center justify-center rounded-full',
                                                'bg-emerald-500 text-white transition-colors hover:bg-emerald-600',
                                            )}
                                        >
                                            <MessageCircle className="size-4" />
                                        </a>
                                    </RaadMobileActions>
                                </RaadMobileCard>
                            ))}
                        </RaadMobileStack>
                    )}
                </CardContent>
                <RaadPagination
                    links={bonuses.links}
                    currentPage={bonuses.current_page}
                    lastPage={bonuses.last_page}
                    perPage={bonuses.per_page}
                    total={bonuses.total}
                    only={['bonuses']}
                />
            </Card>

            {auth.user?.is_admin && eligibleDialogOpen && (
                <EligibleBonusesDialog open onOpenChange={setEligibleDialogOpen} />
            )}
        </div>
    );
}
