import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { CloseButton, Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface RaadDateInputProps {
    id: string;
    value: string;
    onChange: (value: string) => void;
    'aria-label': string;
    className?: string;
}

const weekdayLabels = ['أحد', 'إثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت'];

function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseIsoDate(value: string): Date | null {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function formatDisplayDate(value: string): string {
    const date = parseIsoDate(value);

    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat('ar-SA', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function buildCalendarDays(year: number, month: number): Array<Date | null> {
    const firstDay = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const padding = firstDay.getDay();
    const days: Array<Date | null> = Array.from({ length: padding }, () => null);

    for (let day = 1; day <= daysInMonth; day++) {
        days.push(new Date(year, month, day));
    }

    return days;
}

function isSameDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

export function RaadDateInput({ id, value, onChange, 'aria-label': ariaLabel, className }: RaadDateInputProps) {
    const selectedDate = parseIsoDate(value);
    const [viewDate, setViewDate] = useState(() => selectedDate ?? new Date());

    const monthLabel = new Intl.DateTimeFormat('ar-SA', {
        month: 'long',
        year: 'numeric',
    }).format(viewDate);

    const calendarDays = useMemo(
        () => buildCalendarDays(viewDate.getFullYear(), viewDate.getMonth()),
        [viewDate],
    );

    useEffect(() => {
        const parsed = parseIsoDate(value);

        if (parsed) {
            setViewDate(parsed);
        }
    }, [value]);

    const today = new Date();

    const goToPreviousMonth = () => {
        setViewDate((current) => new Date(current.getFullYear(), current.getMonth() - 1, 1));
    };

    const goToNextMonth = () => {
        setViewDate((current) => new Date(current.getFullYear(), current.getMonth() + 1, 1));
    };

    const selectDate = (date: Date) => {
        onChange(toIsoDate(date));
        setViewDate(date);
    };

    return (
        <Popover className={cn('relative', className)}>
            <PopoverButton
                id={id}
                aria-label={ariaLabel}
                className={cn(
                    'border-input bg-background ring-offset-background focus-visible:ring-ring flex h-11 w-full min-w-0 items-center justify-between rounded-md border px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden sm:h-9 sm:w-36',
                    !value && 'text-muted-foreground',
                )}
            >
                <span dir="ltr" className="truncate">
                    {value ? formatDisplayDate(value) : 'اختر التاريخ'}
                </span>
                <CalendarDays className="text-muted-foreground size-4 shrink-0" />
            </PopoverButton>

            <PopoverPanel
                anchor="bottom end"
                transition
                className="z-50 w-[min(18rem,calc(100vw-2rem))] rounded-md border bg-popover p-3 text-popover-foreground shadow-md [--anchor-gap:6px] transition duration-150 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <Button type="button" variant="ghost" size="icon" className="size-8" onClick={goToPreviousMonth}>
                            <ChevronRight className="size-4" />
                        </Button>
                        <p className="text-sm font-semibold">{monthLabel}</p>
                        <Button type="button" variant="ghost" size="icon" className="size-8" onClick={goToNextMonth}>
                            <ChevronLeft className="size-4" />
                        </Button>
                    </div>

                    <div className="grid grid-cols-7 gap-1 text-center text-xs">
                        {weekdayLabels.map((label) => (
                            <div key={label} className="text-muted-foreground py-1 font-medium">
                                {label}
                            </div>
                        ))}

                        {calendarDays.map((day, index) => {
                            if (day === null) {
                                return <div key={`empty-${index}`} />;
                            }

                            const isSelected = selectedDate !== null && isSameDay(day, selectedDate);
                            const isToday = isSameDay(day, today);

                            return (
                                <CloseButton
                                    key={toIsoDate(day)}
                                    type="button"
                                    onClick={() => selectDate(day)}
                                    className={cn(
                                        'hover:bg-accent hover:text-accent-foreground flex size-8 items-center justify-center rounded-md text-sm transition-colors',
                                        isSelected && 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                        !isSelected && isToday && 'border border-primary/40 text-primary',
                                    )}
                                >
                                    {day.getDate()}
                                </CloseButton>
                            );
                        })}
                    </div>

                    {value && (
                        <div className="border-t pt-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground h-8 w-full"
                                onClick={() => onChange('')}
                            >
                                مسح التاريخ
                            </Button>
                        </div>
                    )}
                </div>
            </PopoverPanel>
        </Popover>
    );
}
