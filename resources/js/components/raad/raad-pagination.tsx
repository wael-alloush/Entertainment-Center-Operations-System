import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RaadPaginationProps {
    links: PaginationLink[];
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    only?: string[];
    queryParams?: Record<string, string | number>;
    className?: string;
}

function visitPage(url: string | null, queryParams?: Record<string, string | number>, only?: string[]): void {
    if (url === null) {
        return;
    }

    const target = new URL(url, window.location.origin);

    if (queryParams !== undefined) {
        Object.entries(queryParams).forEach(([key, value]) => {
            target.searchParams.set(key, String(value));
        });
    }

    router.get(`${target.pathname}${target.search}`, {}, {
        preserveState: true,
        preserveScroll: true,
        ...(only !== undefined ? { only } : {}),
    });
}

export function RaadPagination({ links, currentPage, lastPage, perPage, total, only, queryParams, className }: RaadPaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    const previousLink = links[0] ?? null;
    const nextLink = links[links.length - 1] ?? null;
    const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);

    const pageLinks = links.filter((link) => /^\d+$/.test(link.label.trim()));

    return (
        <div className={cn('flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between', className)}>
            <p className="text-muted-foreground text-sm">
                عرض {from}–{to} من {total}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={previousLink?.url === null}
                    onClick={() => visitPage(previousLink?.url ?? null, only)}
                >
                    <ChevronRight className="size-4" />
                    السابق
                </Button>

                {pageLinks.map((link) => (
                    <Button
                        key={link.label}
                        type="button"
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        className="min-w-9"
                        disabled={link.url === null}
                        onClick={() => visitPage(link.url, queryParams, only)}
                    >
                        {link.label}
                    </Button>
                ))}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={nextLink?.url === null}
                    onClick={() => visitPage(nextLink?.url ?? null, queryParams, only)}
                >
                    التالي
                    <ChevronLeft className="size-4" />
                </Button>
            </div>
        </div>
    );
}
