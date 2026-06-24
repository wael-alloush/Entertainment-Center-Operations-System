import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export function RaadDesktopTable({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('raad-data-table-desktop', className)}>
            <table className="w-full text-sm">{children}</table>
        </div>
    );
}

export function RaadMobileStack({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('raad-data-table-mobile', className)}>{children}</div>;
}

export function RaadMobileCard({
    children,
    onClick,
    className,
}: {
    children: ReactNode;
    onClick?: () => void;
    className?: string;
}) {
    return (
        <article
            className={cn(
                'rounded-xl border border-border/70 bg-card p-3.5 shadow-sm transition-colors',
                onClick && 'cursor-pointer hover:bg-muted/30 active:bg-muted/40',
                className,
            )}
            onClick={onClick}
            onKeyDown={
                onClick
                    ? (event) => {
                          if (event.key === 'Enter' || event.key === ' ') {
                              event.preventDefault();
                              onClick();
                          }
                      }
                    : undefined
            }
            role={onClick ? 'button' : undefined}
            tabIndex={onClick ? 0 : undefined}
        >
            {children}
        </article>
    );
}

export function RaadMobileCardHeader({
    title,
    middle,
    trailing,
    className,
}: {
    title: ReactNode;
    middle?: ReactNode;
    trailing?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex w-full items-center gap-2 border-b border-border/60 pb-2.5',
                middle ? null : trailing ? 'justify-between' : null,
                className,
            )}
        >
            <div className="min-w-0 shrink-0 text-sm font-semibold leading-snug">{title}</div>
            {middle ? <div className="min-w-0 flex-1 text-center text-sm">{middle}</div> : null}
            {trailing ? <div className={cn('shrink-0', middle ? null : 'ms-auto')}>{trailing}</div> : null}
        </div>
    );
}

export function RaadMobileCardTitle({ children, className }: { children: ReactNode; className?: string }) {
    return <RaadMobileCardHeader title={children} className={className} />;
}

export function RaadMobileBody({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mt-2.5 space-y-2.5', className)}>{children}</div>;
}

export function RaadMobileChips({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('flex flex-wrap items-center gap-1.5', className)}>{children}</div>;
}

export function RaadMobileGrid({
    children,
    cols = 2,
    className,
}: {
    children: ReactNode;
    cols?: 2 | 3;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-2', cols === 3 ? 'grid-cols-3' : 'grid-cols-2', className)}>{children}</div>
    );
}

export function RaadMobileStat({
    label,
    children,
    className,
    dir,
}: {
    label: string;
    children: ReactNode;
    className?: string;
    dir?: 'ltr' | 'rtl';
}) {
    return (
        <div className={cn('rounded-lg bg-muted/40 px-2.5 py-2', className)}>
            <p className="text-muted-foreground mb-0.5 text-[11px] leading-tight">{label}</p>
            <div className="text-sm font-medium leading-snug" dir={dir}>
                {children}
            </div>
        </div>
    );
}

export function RaadMobileField({
    label,
    children,
    className,
}: {
    label: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-1', className)}>
            <span className="text-muted-foreground text-[11px]">{label}</span>
            <div className="text-sm">{children}</div>
        </div>
    );
}

export function RaadMobileActions({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('raad-mobile-action-grid mt-3 border-t border-border/60 pt-3 [&_button]:w-full [&_a]:w-full', className)}>
            {children}
        </div>
    );
}

export function RaadTableEmpty({ children, className }: { children: ReactNode; className?: string }) {
    return <p className={cn('text-muted-foreground px-4 py-12 text-center text-sm', className)}>{children}</p>;
}
