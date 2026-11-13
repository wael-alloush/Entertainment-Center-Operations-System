import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { RaadDesktopTable, RaadMobileCard, RaadMobileStack, RaadTableEmpty } from '@/components/raad/raad-responsive-table';
import { jsonFetch } from '@/lib/http';
import { EligibleBonusCustomer, PaginatedEligibleBonusCustomers } from '@/types/domain';
import { ChevronLeft, ChevronRight, Gift, LoaderCircle, Phone, Search, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface EligibleBonusesDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function EligibleBonusesDialog({ open, onOpenChange }: EligibleBonusesDialogProps) {
    const [customers, setCustomers] = useState<PaginatedEligibleBonusCustomers | null>(null);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const fetchCustomers = useCallback(async (nextPage: number, nextSearch: string) => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({ page: String(nextPage) });

            if (nextSearch.trim() !== '') {
                params.set('search', nextSearch.trim());
            }

            const payload = await jsonFetch<{ customers: PaginatedEligibleBonusCustomers }>(
                `${route('invoices.bonuses.eligible-without-pending')}?${params.toString()}`,
            );

            setCustomers(payload.customers);
        } catch (requestError) {
            const status = (requestError as Error & { status?: number }).status;

            if (status === 419) {
                setError('انتهت صلاحية الجلسة. حدّث الصفحة ثم حاول مرة أخرى.');
            } else {
                setError('تعذر تحميل الأرقام المؤهلة.');
            }

            setCustomers({
                data: [],
                links: [],
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 0,
            });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        void fetchCustomers(1, '');
    }, [open, fetchCustomers]);

    useEffect(() => {
        return () => {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (!open) {
            setSearch('');
            setCustomers(null);
            setError(null);
        }
    }, [open]);

    const updateSearch = (value: string) => {
        setSearch(value);

        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        searchDebounceRef.current = setTimeout(() => {
            void fetchCustomers(1, value);
        }, 350);
    };

    const goToPage = (nextPage: number) => {
        void fetchCustomers(nextPage, search);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="raad-dialog-surface max-h-[85vh] overflow-hidden sm:max-w-2xl" dir="rtl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Gift className="size-5 text-violet-600" />
                        أرقام مؤهلة للمكافأة
                    </DialogTitle>
                    <DialogDescription>أرقام جوال استحقت مكافأة ولم تُضف لها مكافأة بعد</DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
                    <Input
                        placeholder="ابحث برقم الجوال..."
                        value={search}
                        onChange={(event) => updateSearch(event.target.value)}
                        className="bg-background pr-10"
                        dir="ltr"
                    />
                </div>

                {error && <p className="text-destructive text-sm">{error}</p>}

                <div className="max-h-[50vh] overflow-y-auto rounded-xl border">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 px-4 py-12 text-sm">
                            <LoaderCircle className="size-4 animate-spin" />
                            جاري التحميل...
                        </div>
                    ) : customers && customers.data.length > 0 ? (
                        <>
                            <RaadDesktopTable>
                                <thead>
                                    <tr className="raad-table-head">
                                        <th className="px-4 py-3 text-right font-medium">رقم الجوال</th>
                                        <th className="px-4 py-3 text-right font-medium">عدد الزيارات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {customers.data.map((customer: EligibleBonusCustomer) => (
                                        <tr key={customer.id} className="border-b transition-colors last:border-0 hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2 font-medium" dir="ltr">
                                                    <Phone className="text-muted-foreground size-4 shrink-0" />
                                                    {customer.phone_number}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1.5 border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                >
                                                    <Users className="size-3.5" />
                                                    {customer.visit_count}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </RaadDesktopTable>

                            <RaadMobileStack className="md:hidden">
                                {customers.data.map((customer: EligibleBonusCustomer) => (
                                    <RaadMobileCard key={customer.id}>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2 font-semibold" dir="ltr">
                                                <Phone className="text-muted-foreground size-4 shrink-0" />
                                                {customer.phone_number}
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                            >
                                                {customer.visit_count} زيارة
                                            </Badge>
                                        </div>
                                    </RaadMobileCard>
                                ))}
                            </RaadMobileStack>
                        </>
                    ) : (
                        <RaadTableEmpty className="py-12">لا توجد أرقام مؤهلة بدون مكافأة</RaadTableEmpty>
                    )}
                </div>

                {customers && customers.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3 border-t pt-3">
                        <p className="text-muted-foreground text-sm">
                            صفحة {customers.current_page} من {customers.last_page} ({customers.total} رقم)
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={customers.current_page <= 1 || loading}
                                onClick={() => goToPage(customers.current_page - 1)}
                            >
                                <ChevronRight className="size-4" />
                                السابق
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={customers.current_page >= customers.last_page || loading}
                                onClick={() => goToPage(customers.current_page + 1)}
                            >
                                التالي
                                <ChevronLeft className="size-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
