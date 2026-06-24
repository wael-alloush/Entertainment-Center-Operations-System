import { computeRemainingMinutes } from '@/lib/format-time';
import { Invoice } from '@/types/domain';
import { useEffect, useState } from 'react';

const TICK_INTERVAL_MS = 1_000;

function withLiveRemainingMinutes<T extends Pick<Invoice, 'status' | 'expected_exit_at_iso' | 'remaining_minutes'>>(
    invoice: T,
): T {
    if (invoice.status !== 'active') {
        return invoice;
    }

    if (invoice.expected_exit_at_iso === undefined || invoice.expected_exit_at_iso === null) {
        return invoice;
    }

    const remainingMinutes = computeRemainingMinutes(invoice.expected_exit_at_iso);

    if (remainingMinutes === invoice.remaining_minutes) {
        return invoice;
    }

    return {
        ...invoice,
        remaining_minutes: remainingMinutes,
    };
}

function useRemainingTimeTick(enabled: boolean): number {
    const [tick, setTick] = useState(0);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const bump = () => {
            setTick((current) => current + 1);
        };

        bump();

        const intervalId = window.setInterval(bump, TICK_INTERVAL_MS);

        const handleVisibilityChange = () => {
            if (!document.hidden) {
                bump();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            window.clearInterval(intervalId);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, [enabled]);

    return tick;
}

export function useLiveRemainingMinutes<T extends Pick<Invoice, 'status' | 'expected_exit_at_iso' | 'remaining_minutes'>>(
    invoices: T[],
    enabled: boolean,
): T[] {
    const tick = useRemainingTimeTick(enabled);

    if (!enabled) {
        return invoices;
    }

    void tick;

    return invoices.map((invoice) => withLiveRemainingMinutes(invoice));
}

export function useLiveRemainingMinute<
    T extends Pick<Invoice, 'status' | 'expected_exit_at_iso' | 'remaining_minutes'> | null,
>(invoice: T, enabled: boolean): T {
    const tick = useRemainingTimeTick(enabled && invoice !== null);

    if (!enabled || invoice === null) {
        return invoice;
    }

    void tick;

    return withLiveRemainingMinutes(invoice);
}
