import { cn } from '@/lib/utils';
import { SessionDuration } from '@/types/domain';
import { Timer } from 'lucide-react';

interface SessionCardProps {
    onClick: () => void;
    boxShadowActive: boolean;
    session: SessionDuration;
    compact?: boolean;
}

export function SessionCard({ onClick, boxShadowActive, session, compact = false }: SessionCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'bg-card hover:bg-accent/40 flex w-full flex-col overflow-hidden rounded-xl border-2 transition-all',
                compact ? 'min-h-16' : 'min-h-20',
                boxShadowActive ? 'ring-2 ring-offset-2' : 'border-border/80',
            )}
            style={{
                borderColor: boxShadowActive ? session.color : undefined,
                boxShadow: boxShadowActive ? `${session.color}40 0 8px 24px` : undefined,
                ['--tw-ring-color' as string]: boxShadowActive ? session.color : undefined,
            }}
        >
            <div
                className={cn(
                    'flex flex-1 flex-col items-center justify-center gap-1.5 px-3',
                    compact ? 'py-3' : 'gap-2 px-4 py-5',
                )}
            >
                <span
                    className={cn(
                        'flex items-center justify-center rounded-full',
                        compact ? 'size-7' : 'size-9',
                    )}
                    style={{ backgroundColor: `${session.color}22`, color: session.color }}
                >
                    <Timer className={compact ? 'size-3.5' : 'size-4'} strokeWidth={2.25} />
                </span>
                <span className={cn('font-semibold', compact ? 'text-xs' : 'text-sm')}>{session.name}</span>
            </div>
            <div className="h-2 w-full" style={{ backgroundColor: session.color }} />
        </button>
    );
}
