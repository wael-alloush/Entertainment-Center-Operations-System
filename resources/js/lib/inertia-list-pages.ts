import { router } from '@inertiajs/react';

const PREFETCHED_LIST_PAGES = [
    'invoices.active',
    'invoices.completed',
    'invoices.bonuses',
    'invoices.credits',
] as const;

export function flushPrefetchedListPages(): void {
    for (const page of PREFETCHED_LIST_PAGES) {
        router.flush(route(page));
    }
}
