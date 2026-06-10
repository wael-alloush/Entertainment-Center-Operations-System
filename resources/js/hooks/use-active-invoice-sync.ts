import { ActiveInvoiceChanges } from '@/types/domain';
import { useEffect, useRef } from 'react';

const SYNC_INTERVAL_MS = 12_000;

export function useActiveInvoiceSync(
    enabled: boolean,
    onSync: (payload: ActiveInvoiceChanges) => void,
    resetKey: string,
): void {
    const syncedAtRef = useRef(new Date().toISOString());
    const onSyncRef = useRef(onSync);

    useEffect(() => {
        onSyncRef.current = onSync;
    }, [onSync]);

    useEffect(() => {
        syncedAtRef.current = new Date().toISOString();
    }, [resetKey]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let timeoutId: ReturnType<typeof setTimeout> | undefined;
        let cancelled = false;

        const schedule = () => {
            timeoutId = setTimeout(tick, SYNC_INTERVAL_MS);
        };

        const tick = async () => {
            if (cancelled) {
                return;
            }

            if (document.hidden) {
                schedule();

                return;
            }

            try {
                const response = await fetch(
                    `${route('invoices.active.changes')}?since=${encodeURIComponent(syncedAtRef.current)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    schedule();

                    return;
                }

                const payload = (await response.json()) as ActiveInvoiceChanges;
                syncedAtRef.current = payload.synced_at;

                if (payload.updated.length > 0 || payload.removed_ids.length > 0) {
                    onSyncRef.current(payload);
                }
            } catch {
                // Ignore transient network errors and retry on the next interval.
            }

            schedule();
        };

        schedule();

        return () => {
            cancelled = true;

            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    }, [enabled]);
}
