export function formatTime12(date: Date): string {
    const hour = date.getHours();
    const minute = date.getMinutes();
    const period = hour < 12 ? 'ص' : 'م';
    const hour12 = hour % 12 || 12;

    return `${hour12.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} ${period}`;
}

export function formatExitTime(
    expectedExitAt: string | null,
    exitedAt: string | null,
    isActive: boolean,
): string {
    if (expectedExitAt) {
        return expectedExitAt;
    }

    if (exitedAt) {
        return exitedAt;
    }

    if (isActive) {
        return 'مفتوح';
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

export function formatRemainingMinutes(minutes: number | null): string {
    if (minutes === null) {
        return 'مفتوح';
    }

    if (minutes <= 0) {
        return 'انتهى';
    }

    return formatHoursAndMinutes(minutes);
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
