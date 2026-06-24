import { InvoiceDetailDialog } from '@/components/raad/invoice-detail-dialog';
import { PreviousVisitsPanel } from '@/components/raad/previous-visits-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PreviousVisitInvoice, PreviousVisitsPayload, SessionDuration } from '@/types/domain';
import { History, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const phonePattern = /^05\d{8}$/;

interface InvoiceCreatePreviousVisitsProps {
    phoneNumber: string;
    sessionDurations: SessionDuration[];
    showReceptionActions?: boolean;
    showReactivate?: boolean;
    showDeleteCompleted?: boolean;
}

export function InvoiceCreatePreviousVisits({
    phoneNumber,
    sessionDurations,
    showReceptionActions = false,
    showReactivate = false,
    showDeleteCompleted = false,
}: InvoiceCreatePreviousVisitsProps) {
    const [open, setOpen] = useState(false);
    const [payload, setPayload] = useState<PreviousVisitsPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [pageLoading, setPageLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [detailInvoiceId, setDetailInvoiceId] = useState<number | null>(null);
    const [detailExpiredBonusId, setDetailExpiredBonusId] = useState<number | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const requestIdRef = useRef(0);

    useEffect(() => {
        setOpen(false);
        setPayload(null);
        setLoading(false);
        setPageLoading(false);
        setPage(1);
    }, [phoneNumber]);

    const loadVisits = useCallback(
        async (targetPage: number, initialLoad: boolean) => {
            if (!phonePattern.test(phoneNumber)) {
                return;
            }

            if (initialLoad) {
                setLoading(true);
                setPayload(null);
            } else {
                setPageLoading(true);
            }

            const requestId = ++requestIdRef.current;

            try {
                const url = new URL(route('invoices.previous-visits'), window.location.origin);
                url.searchParams.set('phone_number', phoneNumber);
                url.searchParams.set('page', String(targetPage));

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok || requestId !== requestIdRef.current) {
                    return;
                }

                setPayload((await response.json()) as PreviousVisitsPayload);
                setPage(targetPage);
            } catch {
                if (requestId === requestIdRef.current && initialLoad) {
                    setPayload(null);
                }
            } finally {
                if (requestId === requestIdRef.current) {
                    setLoading(false);
                    setPageLoading(false);
                }
            }
        },
        [phoneNumber],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        void loadVisits(1, true);
    }, [open, loadVisits]);

    const openVisitDetail = (visit: PreviousVisitInvoice) => {
        if (visit.is_expired_bonus) {
            setDetailInvoiceId(null);
            setDetailExpiredBonusId(visit.id);
        } else {
            setDetailExpiredBonusId(null);
            setDetailInvoiceId(visit.id);
        }

        setDetailOpen(true);
    };

    if (!phonePattern.test(phoneNumber)) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                title="الفواتير السابقة"
                className="h-9 gap-1.5 text-amber-700 hover:bg-amber-500/10 dark:text-amber-300"
                onClick={() => setOpen(true)}
            >
                <History className="size-4" />
                <span className="text-xs">سابقة</span>
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent
                    className="flex max-h-[min(calc(100dvh-2rem),90vh)] flex-col gap-0 overflow-hidden p-0 md:w-[50vw] md:max-w-[50vw] [&>button.absolute]:hidden"
                    dir="rtl"
                >
                    <DialogHeader className="shrink-0 border-b px-4 py-2.5 text-right md:py-3">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="raad-icon-chip bg-amber-500/12 text-amber-600 dark:text-amber-400">
                                    <History className="size-5" />
                                </span>
                                <div>
                                    <DialogTitle className="text-sm md:text-base">الزيارات السابقة</DialogTitle>
                                    <DialogDescription className="text-xs">
                                        سجل زيارات العميل لهذا الرقم
                                    </DialogDescription>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground shrink-0"
                                onClick={() => setOpen(false)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    </DialogHeader>
                    <div className="flex-1 overflow-y-auto p-3 md:p-4">
                        <PreviousVisitsPanel
                            payload={payload}
                            loading={loading}
                            pageLoading={pageLoading}
                            onVisitClick={openVisitDetail}
                            onPageChange={(nextPage) => void loadVisits(nextPage, false)}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <InvoiceDetailDialog
                invoiceId={detailInvoiceId}
                expiredBonusId={detailExpiredBonusId}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                isAdmin
                showReceptionActions={showReceptionActions}
                showReactivate={showReactivate}
                showDeleteCompleted={showDeleteCompleted}
                sessionDurations={sessionDurations}
            />
        </>
    );
}
