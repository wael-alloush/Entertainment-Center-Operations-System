import { cn } from '@/lib/utils';
import { SessionDuration } from '@/types/domain';
import { Timer } from 'lucide-react';

interface SessionCardProps {
    onClick: () => void;
    boxShadowActive: boolean;
    session: SessionDuration;
}

export function SessionCard({ onClick, boxShadowActive, session }: SessionCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'bg-card hover:bg-accent/40 flex w-full flex-col overflow-hidden rounded-xl border-2 transition-all',
                boxShadowActive ? 'ring-2 ring-offset-2' : 'border-border/80',
            )}
            style={{
                borderColor: boxShadowActive ? session.color : undefined,
                boxShadow: boxShadowActive ? `${session.color}40 0 8px 24px` : undefined,
                ['--tw-ring-color' as string]: boxShadowActive ? session.color : undefined,
            }}
        >
            <div className="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-5">
                <span
                    className="flex size-9 items-center justify-center rounded-full"
                    style={{ backgroundColor: `${session.color}22`, color: session.color }}
                >
                    <Timer className="size-4" strokeWidth={2.25} />
                </span>
                <span className="text-sm font-semibold">{session.name}</span>
            </div>
            <div className="h-2 w-full" style={{ backgroundColor: session.color }} />
        </button>
    );
}
