import { useConfirm } from '@/components/raad/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { PendingCredit, PendingBonus, SessionDuration } from '@/types/domain';
import { flushPrefetchedListPages } from '@/lib/inertia-list-pages';
import { router } from '@inertiajs/react';
import { Baby, CalendarDays, Gift, LoaderCircle, Timer, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface PendingRecordsPayload {
    bonuses: PendingBonus[];
    credits: PendingCredit[];
}

interface InvoiceCreatePendingRecordsProps {
    phoneNumber: string;
    onActivated?: () => void;
}

const phonePattern = /^05\d{8}$/;

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

export function InvoiceCreatePendingRecords({ phoneNumber, onActivated }: InvoiceCreatePendingRecordsProps) {
    const confirm = useConfirm();
    const [records, setRecords] = useState<PendingRecordsPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [activatingBonusId, setActivatingBonusId] = useState<number | null>(null);
    const [activatingCreditId, setActivatingCreditId] = useState<number | null>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const requestIdRef = useRef(0);

    useEffect(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        if (!phonePattern.test(phoneNumber)) {
            setRecords(null);
            setLoading(false);

            return;
        }

        setLoading(true);
        const requestId = ++requestIdRef.current;

        debounceRef.current = setTimeout(async () => {
            try {
                const response = await fetch(
                    `${route('invoices.pending-records')}?phone_number=${encodeURIComponent(phoneNumber)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok || requestId !== requestIdRef.current) {
                    return;
                }

                const payload = (await response.json()) as PendingRecordsPayload;
                setRecords(payload);
            } catch {
                if (requestId === requestIdRef.current) {
                    setRecords(null);
                }
            } finally {
                if (requestId === requestIdRef.current) {
                    setLoading(false);
                }
            }
        }, 350);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [phoneNumber]);

    const activateBonus = async (bonus: PendingBonus) => {
        const confirmed = await confirm({
            title: 'تفعيل المكافأة',
            description: `تفعيل المكافأة لرقم ${bonus.phone_number}؟ سيتم إنشاء فاتورة نشطة.`,
            confirmLabel: 'تفعيل',
        });

        if (!confirmed) {
            return;
        }

        setActivatingBonusId(bonus.id);
        router.post(route('customer-bonuses.activate', bonus.id), {}, {
            reset: ['invoices', 'summary', 'bonuses', 'pendingBonusCount', 'eligibleBonusWithoutPendingCount'],
            onSuccess: () => {
                flushPrefetchedListPages();
                onActivated?.();
            },
            onFinish: () => setActivatingBonusId(null),
        });
    };

    const activateCredit = async (credit: PendingCredit) => {
        const confirmed = await confirm({
            title: 'تفعيل الرصيد',
            description: `تفعيل الرصيد لرقم ${credit.phone_number}؟ سيتم إنشاء فاتورة نشطة.`,
            confirmLabel: 'تفعيل',
        });

        if (!confirmed) {
            return;
        }

        setActivatingCreditId(credit.id);
        router.post(route('customer-credits.activate', credit.id), {}, {
            reset: ['invoices', 'summary', 'credits', 'pendingCreditCount'],
            onSuccess: () => {
                flushPrefetchedListPages();
                onActivated?.();
            },
            onFinish: () => setActivatingCreditId(null),
        });
    };

    if (!phonePattern.test(phoneNumber)) {
        return null;
    }

    if (loading) {
        return (
            <div className="text-muted-foreground flex items-center gap-2 rounded-lg border border-dashed px-4 py-3 text-sm">
                <LoaderCircle className="size-4 animate-spin" />
                جاري البحث عن مكافآت وأرصدة معلقة...
            </div>
        );
    }

    if (records === null) {
        return null;
    }

    const hasBonuses = records.bonuses.length > 0;
    const hasCredits = records.credits.length > 0;

    if (!hasBonuses && !hasCredits) {
        return null;
    }

    return (
        <div className="space-y-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
            <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                يوجد مكافآت أو أرصدة غير مفعلة لهذا الرقم
            </p>

            {hasBonuses && (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 text-sm font-semibold text-violet-700 dark:text-violet-300">
                        <Gift className="size-4" />
                        مكافآت غير مفعلة ({records.bonuses.length})
                    </div>
                    <div className="space-y-2">
                        {records.bonuses.map((bonus) => (
                            <div
                                key={bonus.id}
                                className="bg-background flex flex-col gap-2 rounded-lg border px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Badge
                                        variant="outline"
                                        className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                    >
                                        <Baby className="size-3.5" />
                                        {bonus.children_count} طفل
                                    </Badge>
                                    <SessionDurationBadge session={bonus.session_duration} />
                                    {bonus.expires_at && (
                                        <span className="text-muted-foreground inline-flex items-center gap-1 text-xs" dir="ltr">
                                            <CalendarDays className="size-3.5" />
                                            {bonus.expires_at}
                                        </span>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="min-h-10 w-full bg-violet-600 hover:bg-violet-700 sm:w-auto"
                                    disabled={activatingBonusId === bonus.id}
                                    onClick={() => activateBonus(bonus)}
                                >
                                    {activatingBonusId === bonus.id ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        'تفعيل المكافأة'
                                    )}
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {hasCredits && (
                <div className={cn('space-y-2', hasBonuses && 'border-t border-amber-500/20 pt-3')}>
                    <div className="flex items-center gap-2 text-sm font-semibold text-sky-700 dark:text-sky-300">
                        <Wallet className="size-4" />
                        أرصدة غير مفعلة ({records.credits.length})
                    </div>
                    <div className="space-y-2">
                        {records.credits.map((credit) => (
                            <div
                                key={credit.id}
                                className="bg-background flex flex-col gap-2 rounded-lg border px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Badge
                                        variant="outline"
                                        className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                    >
                                        <Baby className="size-3.5" />
                                        {credit.children_count} طفل
                                    </Badge>
                                    <SessionDurationBadge session={credit.session_duration} />
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-xs',
                                            credit.counts_as_visit
                                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'border-muted-foreground/30 bg-muted/40 text-muted-foreground',
                                        )}
                                    >
                                        {credit.counts_as_visit ? 'يُحتسب زيارة' : 'لا يُحتسب زيارة'}
                                    </Badge>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="min-h-10 w-full bg-sky-600 hover:bg-sky-700 sm:w-auto"
                                    disabled={activatingCreditId === credit.id}
                                    onClick={() => activateCredit(credit)}
                                >
                                    {activatingCreditId === credit.id ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        'تفعيل الرصيد'
                                    )}
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
