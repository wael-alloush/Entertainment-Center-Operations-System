import { useConfirm } from '@/components/raad/confirm-dialog';
import { SessionCard } from '@/components/raad/session-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NumericInput } from '@/components/ui/numeric-input';
import { PhoneInput } from '@/components/ui/phone-input';
import { Label } from '@/components/ui/label';
import {
    RaadDesktopTable,
    RaadMobileActions,
    RaadMobileBody,
    RaadMobileCard,
    RaadMobileCardHeader,
    RaadMobileChips,
    RaadMobileStack,
    RaadMobileStat,
    RaadTableEmpty,
} from '@/components/raad/raad-responsive-table';
import { RaadPagination } from '@/components/raad/raad-pagination';
import { flushPrefetchedListPages } from '@/lib/inertia-list-pages';
import { cn } from '@/lib/utils';
import { InvoiceFilters, PaginatedPendingCredits, PendingCredit, SessionDuration } from '@/types/domain';
import { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Baby,
    CalendarDays,
    CheckCircle2,
    CircleOff,
    LoaderCircle,
    Pencil,
    Phone,
    Plus,
    Search,
    Timer,
    Trash2,
    Wallet,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

interface PendingCreditsTableProps {
    credits: PaginatedPendingCredits;
    filters: InvoiceFilters;
    sessionDurations: SessionDuration[];
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

export function PendingCreditsTable({ credits, filters, sessionDurations }: PendingCreditsTableProps) {
    const confirm = useConfirm();
    const { auth } = usePage<SharedData>().props;
    const canManageCredit = auth.user?.is_admin ?? false;
    const [localFilters, setLocalFilters] = useState(filters);
    const [activatingId, setActivatingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [detailMode, setDetailMode] = useState<'view' | 'edit'>('view');
    const [selectedCredit, setSelectedCredit] = useState<PendingCredit | null>(null);
    const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);
    const [selectedEditSessionId, setSelectedEditSessionId] = useState<number | null>(null);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        phone_number: '',
        children_count: 1,
        session_duration_id: 0,
        counts_as_visit: true as boolean,
    });

    const {
        data: editData,
        setData: setEditData,
        patch,
        processing: editProcessing,
        errors: editErrors,
        reset: resetEdit,
    } = useForm({
        phone_number: '',
        children_count: 1,
        session_duration_id: 0,
        counts_as_visit: true as boolean,
    });

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

        router.get(route('invoices.credits'), params, {
            preserveState: true,
            replace: true,
            reset: ['credits'],
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
        router.get(route('invoices.credits'), {}, { preserveState: true, replace: true, reset: ['credits'] });
    };

    const openCreateDialog = () => {
        reset();
        setData({
            phone_number: '',
            children_count: 1,
            session_duration_id: 0,
            counts_as_visit: true,
        });
        setSelectedSessionId(null);
        setCreateOpen(true);
    };

    const submitCredit = (event: FormEvent) => {
        event.preventDefault();

        if (!selectedSessionId) {
            return;
        }

        post(route('credits.store'), {
            preserveScroll: true,
            reset: ['credits', 'pendingCreditCount'],
            onSuccess: () => {
                flushPrefetchedListPages();
                setCreateOpen(false);
                reset();
                setSelectedSessionId(null);
            },
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

        setActivatingId(credit.id);
        router.post(route('customer-credits.activate', credit.id), {}, {
            preserveScroll: true,
            reset: ['credits', 'invoices', 'summary', 'pendingCreditCount'],
            onSuccess: () => {
                flushPrefetchedListPages();
                closeDetail();
            },
            onFinish: () => setActivatingId(null),
        });
    };

    const openDetail = (credit: PendingCredit) => {
        setSelectedCredit(credit);
        setDetailMode('view');
        setDetailOpen(true);
    };

    const closeDetail = () => {
        setDetailOpen(false);
        setSelectedCredit(null);
        setDetailMode('view');
        resetEdit();
        setSelectedEditSessionId(null);
    };

    const startEditFromDetail = () => {
        if (!selectedCredit) {
            return;
        }

        resetEdit();
        setEditData({
            phone_number: selectedCredit.phone_number,
            children_count: selectedCredit.children_count,
            session_duration_id: selectedCredit.session_duration.id,
            counts_as_visit: selectedCredit.counts_as_visit,
        });
        setSelectedEditSessionId(selectedCredit.session_duration.id);
        setDetailMode('edit');
    };

    const submitEditCredit = (event: FormEvent) => {
        event.preventDefault();

        if (!selectedCredit || !selectedEditSessionId) {
            return;
        }

        patch(route('customer-credits.update', selectedCredit.id), {
            preserveScroll: true,
            reset: ['credits'],
            onSuccess: () => {
                flushPrefetchedListPages();
                closeDetail();
            },
        });
    };

    const deleteCredit = async (credit: PendingCredit) => {
        const confirmed = await confirm({
            title: 'حذف الرصيد',
            description: `حذف الرصيد لرقم ${credit.phone_number}؟ لا يمكن التراجع.`,
            confirmLabel: 'حذف',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        setDeletingId(credit.id);
        router.delete(route('customer-credits.destroy', credit.id), {
            preserveScroll: true,
            reset: ['credits', 'pendingCreditCount'],
            onSuccess: () => {
                flushPrefetchedListPages();
                closeDetail();
            },
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="raad-page-title flex items-center gap-2">
                        <span className="raad-icon-chip bg-sky-500/12 text-sky-600 dark:text-sky-400">
                            <Wallet className="size-5" strokeWidth={2.25} />
                        </span>
                        الرصيد
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">أرصدة العملاء غير المفعلة — تفعيلها يُنشئ فاتورة نشطة</p>
                </div>
                {canManageCredit && (
                    <Button type="button" className="min-h-11 w-full shadow-md shadow-primary/20 sm:w-auto" onClick={openCreateDialog}>
                        <Plus className="size-4" />
                        إضافة رصيد
                    </Button>
                )}
            </div>

            <Card className="raad-card-surface">
                <CardHeader className="space-y-4 border-b pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-bold">الأرصدة المعلقة</h2>
                        {localFilters.search && (
                            <Button type="button" variant="ghost" size="sm" className="text-muted-foreground" onClick={clearFilters}>
                                <X className="size-4" />
                                مسح البحث
                            </Button>
                        )}
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
                                <th className="px-4 py-3 text-right font-medium">احتساب زيارة</th>
                                <th className="px-4 py-3 text-right font-medium">تاريخ الإضافة</th>
                                <th className="px-4 py-3 text-center font-medium">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            {credits.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        لا توجد أرصدة معلقة
                                    </td>
                                </tr>
                            ) : (
                                credits.data.map((credit) => (
                                    <tr
                                        key={credit.id}
                                        className="hover:bg-muted/40 cursor-pointer border-b transition-colors last:border-0"
                                        onClick={() => openDetail(credit)}
                                    >
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-sky-500/30 bg-sky-500/10 font-medium text-sky-700 dark:text-sky-300"
                                                dir="ltr"
                                            >
                                                <Phone className="size-3.5" />
                                                {credit.phone_number}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                <Baby className="size-3.5" />
                                                {credit.children_count} طفل
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <SessionDurationBadge session={credit.session_duration} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {credit.counts_as_visit ? (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                >
                                                    <CheckCircle2 className="size-3.5" />
                                                    نعم
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1.5 border-muted-foreground/30 bg-muted/40 text-muted-foreground"
                                                >
                                                    <CircleOff className="size-3.5" />
                                                    لا
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-muted-foreground inline-flex items-center gap-1.5" dir="ltr">
                                                <CalendarDays className="size-3.5" />
                                                {credit.entered_at}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-center">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="bg-sky-600 hover:bg-sky-700"
                                                    disabled={activatingId === credit.id}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        activateCredit(credit);
                                                    }}
                                                >
                                                    {activatingId === credit.id ? (
                                                        <LoaderCircle className="size-4 animate-spin" />
                                                    ) : (
                                                        <Wallet className="size-4" />
                                                    )}
                                                    تفعيل
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </RaadDesktopTable>

                    {credits.data.length === 0 ? (
                        <RaadTableEmpty className="md:hidden">لا توجد أرصدة معلقة</RaadTableEmpty>
                    ) : (
                        <RaadMobileStack>
                            {credits.data.map((credit) => (
                                <RaadMobileCard key={credit.id} onClick={() => openDetail(credit)}>
                                    <RaadMobileCardHeader
                                        title={
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-sky-500/30 bg-sky-500/10 text-base font-semibold text-sky-700 dark:text-sky-300"
                                                dir="ltr"
                                            >
                                                <Phone className="size-3.5" />
                                                {credit.phone_number}
                                            </Badge>
                                        }
                                        trailing={
                                            credit.counts_as_visit ? (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                >
                                                    <CheckCircle2 className="size-3" />
                                                    زيارة
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary" className="gap-1 text-muted-foreground">
                                                    <CircleOff className="size-3" />
                                                    بدون زيارة
                                                </Badge>
                                            )
                                        }
                                    />
                                    <RaadMobileBody>
                                        <RaadMobileChips>
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300"
                                            >
                                                <Baby className="size-3.5" />
                                                {credit.children_count} طفل
                                            </Badge>
                                            <SessionDurationBadge session={credit.session_duration} />
                                        </RaadMobileChips>
                                        <RaadMobileStat label="تاريخ الإضافة" dir="ltr">
                                            {credit.entered_at}
                                        </RaadMobileStat>
                                    </RaadMobileBody>
                                    <RaadMobileActions>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="min-h-11 bg-sky-600 hover:bg-sky-700"
                                            disabled={activatingId === credit.id}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                activateCredit(credit);
                                            }}
                                        >
                                            {activatingId === credit.id ? (
                                                <LoaderCircle className="size-4 animate-spin" />
                                            ) : (
                                                <Wallet className="size-4" />
                                            )}
                                            تفعيل
                                        </Button>
                                    </RaadMobileActions>
                                </RaadMobileCard>
                            ))}
                        </RaadMobileStack>
                    )}
                </CardContent>
                <RaadPagination
                    links={credits.links}
                    currentPage={credits.current_page}
                    lastPage={credits.last_page}
                    perPage={credits.per_page}
                    total={credits.total}
                    only={['credits']}
                />
            </Card>

            <Dialog open={detailOpen} onOpenChange={(open) => !open && closeDetail()}>
                <DialogContent className="raad-dialog-surface">
                    {detailMode === 'view' && selectedCredit && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Wallet className="text-sky-600 size-5" />
                                    تفاصيل الرصيد
                                </DialogTitle>
                                <DialogDescription>رصيد معلق بانتظار التفعيل</DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                        <p className="text-muted-foreground mb-1 text-xs">رقم الجوال</p>
                                        <p className="font-semibold" dir="ltr">
                                            {selectedCredit.phone_number}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                        <p className="text-muted-foreground mb-1 text-xs">عدد الأطفال</p>
                                        <p className="font-semibold">{selectedCredit.children_count} طفل</p>
                                    </div>
                                    <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                        <p className="text-muted-foreground mb-1 text-xs">مدة الجلسة</p>
                                        <SessionDurationBadge session={selectedCredit.session_duration} />
                                    </div>
                                    <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                        <p className="text-muted-foreground mb-1 text-xs">احتساب زيارة</p>
                                        {selectedCredit.counts_as_visit ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                            >
                                                <CheckCircle2 className="size-3.5" />
                                                نعم
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-muted-foreground/30 bg-muted/40 text-muted-foreground"
                                            >
                                                <CircleOff className="size-3.5" />
                                                لا
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                                    <p className="text-muted-foreground mb-1 text-xs">تاريخ الإضافة</p>
                                    <p className="font-medium" dir="ltr">
                                        {selectedCredit.entered_at}
                                    </p>
                                </div>
                            </div>
                            <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-between">
                                <div className="flex flex-wrap gap-2">
                                    {canManageCredit && (
                                        <>
                                            <Button type="button" variant="outline" onClick={startEditFromDetail}>
                                                <Pencil className="size-4" />
                                                تعديل
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={deletingId === selectedCredit.id}
                                                onClick={() => deleteCredit(selectedCredit)}
                                            >
                                                {deletingId === selectedCredit.id ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <Trash2 className="size-4" />
                                                )}
                                                حذف
                                            </Button>
                                        </>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    className="bg-sky-600 hover:bg-sky-700"
                                    disabled={activatingId === selectedCredit.id}
                                    onClick={() => activateCredit(selectedCredit)}
                                >
                                    {activatingId === selectedCredit.id ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        <Wallet className="size-4" />
                                    )}
                                    تفعيل الرصيد
                                </Button>
                            </DialogFooter>
                        </>
                    )}

                    {detailMode === 'edit' && selectedCredit && canManageCredit && (
                        <>
                            <DialogHeader>
                                <DialogTitle>تعديل الرصيد</DialogTitle>
                                <DialogDescription>عدّل بيانات الرصيد المعلق قبل التفعيل</DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submitEditCredit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit_credit_phone">رقم الجوال</Label>
                                <PhoneInput
                                    id="edit_credit_phone"
                                    value={editData.phone_number}
                                    onChange={(event) => setEditData('phone_number', event.target.value)}
                                    placeholder="05xxxxxxxx"
                                    required
                                />
                                {editErrors.phone_number && <p className="text-destructive text-sm">{editErrors.phone_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit_credit_children">عدد الأطفال</Label>
                                <NumericInput
                                    id="edit_credit_children"
                                    maxLength={2}
                                    value={editData.children_count}
                                    onChange={(event) => setEditData('children_count', Number(event.target.value))}
                                    required
                                />
                                {editErrors.children_count && <p className="text-destructive text-sm">{editErrors.children_count}</p>}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>مدة الجلسة</Label>
                            <div className="raad-session-grid">
                                {sessionDurations.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        boxShadowActive={selectedEditSessionId === session.id}
                                        onClick={() => {
                                            setSelectedEditSessionId(session.id);
                                            setEditData('session_duration_id', session.id);
                                        }}
                                    />
                                ))}
                            </div>
                            {editErrors.session_duration_id && (
                                <p className="text-destructive text-sm">{editErrors.session_duration_id}</p>
                            )}
                        </div>
                        <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                            <Label htmlFor="edit_counts_as_visit" className="cursor-pointer font-medium">
                                احتساب كزيارة
                            </Label>
                            <button
                                id="edit_counts_as_visit"
                                type="button"
                                role="switch"
                                aria-checked={editData.counts_as_visit}
                                className={cn(
                                    'inline-flex h-7 w-12 shrink-0 items-center rounded-full p-0.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                    editData.counts_as_visit ? 'justify-end bg-emerald-500' : 'justify-start bg-muted-foreground/30',
                                )}
                                onClick={() => setEditData('counts_as_visit', !editData.counts_as_visit)}
                            >
                                <span className="block size-5 rounded-full bg-white shadow-sm" />
                            </button>
                        </div>
                        {editErrors.counts_as_visit && <p className="text-destructive text-sm">{editErrors.counts_as_visit}</p>}
                                <DialogFooter>
                                    <Button type="button" variant="outline" onClick={() => setDetailMode('view')}>
                                        إلغاء
                                    </Button>
                                    <Button type="submit" disabled={editProcessing || !selectedEditSessionId}>
                                        {editProcessing ? <LoaderCircle className="size-4 animate-spin" /> : 'حفظ التعديلات'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {canManageCredit && (
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="raad-dialog-surface">
                    <DialogHeader>
                        <DialogTitle>إضافة رصيد</DialogTitle>
                        <DialogDescription>أدخل بيانات العميل واختر مدة اللعب</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCredit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="credit_phone">رقم الجوال</Label>
                                <PhoneInput
                                    id="credit_phone"
                                    value={data.phone_number}
                                    onChange={(event) => setData('phone_number', event.target.value)}
                                    placeholder="05xxxxxxxx"
                                    required
                                />
                                {errors.phone_number && <p className="text-destructive text-sm">{errors.phone_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="credit_children">عدد الأطفال</Label>
                                <NumericInput
                                    id="credit_children"
                                    maxLength={2}
                                    value={data.children_count}
                                    onChange={(event) => setData('children_count', Number(event.target.value))}
                                    required
                                />
                                {errors.children_count && <p className="text-destructive text-sm">{errors.children_count}</p>}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>مدة الجلسة</Label>
                            <div className="raad-session-grid">
                                {sessionDurations.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        session={session}
                                        boxShadowActive={selectedSessionId === session.id}
                                        onClick={() => {
                                            setSelectedSessionId(session.id);
                                            setData('session_duration_id', session.id);
                                        }}
                                    />
                                ))}
                            </div>
                            {errors.session_duration_id && (
                                <p className="text-destructive text-sm">{errors.session_duration_id}</p>
                            )}
                        </div>
                        <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                            <Label htmlFor="counts_as_visit" className="cursor-pointer font-medium">
                                احتساب كزيارة
                            </Label>
                            <button
                                id="counts_as_visit"
                                type="button"
                                role="switch"
                                aria-checked={data.counts_as_visit}
                                title={
                                    data.counts_as_visit
                                        ? 'تُحسب كزيارة إذا كان اليوم بلا فواتير أخرى'
                                        : 'لا تُضاف لعدد الزيارات'
                                }
                                className={cn(
                                    'inline-flex h-7 w-12 shrink-0 items-center rounded-full p-0.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                    data.counts_as_visit ? 'justify-end bg-emerald-500' : 'justify-start bg-muted-foreground/30',
                                )}
                                onClick={() => setData('counts_as_visit', !data.counts_as_visit)}
                            >
                                <span className="block size-5 rounded-full bg-white shadow-sm" />
                            </button>
                        </div>
                        {errors.counts_as_visit && <p className="text-destructive text-sm">{errors.counts_as_visit}</p>}
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={processing || !selectedSessionId}>
                                {processing ? <LoaderCircle className="size-4 animate-spin" /> : 'حفظ الرصيد'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            )}
        </div>
    );
}
