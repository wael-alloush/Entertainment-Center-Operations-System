export function formatTime12(date: Date): string {
    const hour = date.getHours();
    const minute = date.getMinutes();
    const period = hour < 12 ? 'ص' : 'م';
    const hour12 = hour % 12 || 12;

    return `${hour12.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} ${period}`;
}

export function parseTimeString(value: string): { hours: number; minutes: number } | null {
    if (!value) {
        return null;
    }

    const match = value.match(/^(\d{1,2}):(\d{2})/);

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return { hours, minutes };
}

export function formatTimeString24(hours: number, minutes: number): string {
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

export function to12Hour(hours24: number): { hour12: number; isPm: boolean } {
    return {
        hour12: hours24 % 12 || 12,
        isPm: hours24 >= 12,
    };
}

export function to24Hour(hour12: number, isPm: boolean): number {
    if (hour12 === 12) {
        return isPm ? 12 : 0;
    }

    return isPm ? hour12 + 12 : hour12;
}

export function formatTimeString12(value: string): string {
    const parsed = parseTimeString(value);

    if (!parsed) {
        return '';
    }

    const { hour12, isPm } = to12Hour(parsed.hours);

    return `${String(hour12).padStart(2, '0')}:${String(parsed.minutes).padStart(2, '0')} ${isPm ? 'م' : 'ص'}`;
}

export function formatExitTime(
    expectedExitAt: string | null,
    exitedAt: string | null,
    isActive: boolean,
): string {
    if (!isActive && exitedAt) {
        return exitedAt;
    }

    if (isActive) {
        if (expectedExitAt) {
            return expectedExitAt;
        }

        return 'مفتوح';
    }

    if (expectedExitAt) {
        return expectedExitAt;
    }

    if (exitedAt) {
        return exitedAt;
    }

    return '—';
}

function formatHoursAndMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours === 0) {
        return `${mins} د`;
    }

    if (mins === 0) {
        return `${hours} س`;
    }

    return `${hours} س ${mins} د`;
}

export function computeRemainingMinutes(expectedExitAtIso: string | null | undefined): number | null {
    if (!expectedExitAtIso) {
        return null;
    }

    const exitAt = new Date(expectedExitAtIso);

    if (Number.isNaN(exitAt.getTime())) {
        return null;
    }

    return Math.floor((exitAt.getTime() - Date.now()) / 60_000);
}

export function formatRemainingMinutes(minutes: number | null): string {
    if (minutes === null) {
        return 'مفتوح';
    }

    if (minutes <= 0) {
        return 'انتهى';
    }

    return formatHoursAndMinutes(minutes);
}

export type RemainingTimeUrgency = 'open' | 'normal' | 'warning' | 'expired';

export function remainingTimeUrgency(minutes: number | null): RemainingTimeUrgency {
    if (minutes === null) {
        return 'open';
    }

    if (minutes <= 0) {
        return 'expired';
    }

    if (minutes <= 5) {
        return 'warning';
    }

    return 'normal';
}

export function remainingTimeRowClass(minutes: number | null): string {
    switch (remainingTimeUrgency(minutes)) {
        case 'expired':
            return 'bg-rose-500/12 hover:bg-rose-500/16 dark:bg-rose-500/18 dark:hover:bg-rose-500/22';
        case 'warning':
            return 'bg-amber-400/15 hover:bg-amber-400/20 dark:bg-amber-500/12 dark:hover:bg-amber-500/16';
        default:
            return '';
    }
}

export function remainingTimeTextClass(minutes: number | null): string {
    switch (remainingTimeUrgency(minutes)) {
        case 'open':
            return 'text-muted-foreground';
        case 'expired':
            return 'font-semibold text-rose-600 dark:text-rose-400';
        case 'warning':
            return 'font-semibold text-amber-700 dark:text-amber-300';
        default:
            return 'text-emerald-600 dark:text-emerald-400';
    }
}

export function remainingTimeBadgeClass(minutes: number | null): string {
    switch (remainingTimeUrgency(minutes)) {
        case 'open':
            return 'bg-muted text-muted-foreground';
        case 'expired':
            return 'bg-rose-500/15 text-rose-700 ring-1 ring-inset ring-rose-500/30 dark:text-rose-300';
        case 'warning':
            return 'bg-amber-400/20 text-amber-800 ring-1 ring-inset ring-amber-500/35 dark:text-amber-200';
        default:
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }
}

export function formatLateMinutes(minutes: number | null): string | null {
    if (minutes === null) {
        return null;
    }

    if (minutes > 0) {
        return `متأخر ${formatHoursAndMinutes(minutes)}`;
    }

    if (minutes < 0) {
        return `حضر قبل ${formatHoursAndMinutes(Math.abs(minutes))}`;
    }

    return 'في الوقت';
}
