import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    formatTimeString12,
    formatTimeString24,
    parseTimeString,
    to12Hour,
    to24Hour,
} from '@/lib/format-time';
import { CloseButton, Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { ChevronDown, ChevronUp, Clock3 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface RaadTimeInputProps {
    id: string;
    value: string;
    onChange: (value: string) => void;
    'aria-label': string;
    className?: string;
    required?: boolean;
}

const presets = [
    { value: '14:00', label: '2:00 م' },
    { value: '15:00', label: '3:00 م' },
    { value: '16:00', label: '4:00 م' },
    { value: '17:00', label: '5:00 م' },
];

export function RaadTimeInput({ id, value, onChange, 'aria-label': ariaLabel, className, required = false }: RaadTimeInputProps) {
    const parsed = parseTimeString(value);
    const initial = parsed ? to12Hour(parsed.hours) : { hour12: 8, isPm: false };

    const [hour12, setHour12] = useState(initial.hour12);
    const [minutes, setMinutes] = useState(parsed?.minutes ?? 0);
    const [isPm, setIsPm] = useState(initial.isPm);

    useEffect(() => {
        const next = parseTimeString(value);

        if (next) {
            const next12 = to12Hour(next.hours);

            setHour12(next12.hour12);
            setMinutes(next.minutes);
            setIsPm(next12.isPm);
        }
    }, [value]);

    const applyTime = (nextHour12: number, nextMinutes: number, nextIsPm: boolean) => {
        const normalizedHour12 = ((nextHour12 - 1 + 12) % 12) + 1;
        const normalizedMinutes = ((nextMinutes % 60) + 60) % 60;
        const hours24 = to24Hour(normalizedHour12, nextIsPm);

        setHour12(normalizedHour12);
        setMinutes(normalizedMinutes);
        setIsPm(nextIsPm);
        onChange(formatTimeString24(hours24, normalizedMinutes));
    };

    const adjustHours = (delta: number) => {
        applyTime(hour12 + delta, minutes, isPm);
    };

    const adjustMinutes = (delta: number) => {
        applyTime(hour12, minutes + delta, isPm);
    };

    const setPeriod = (nextIsPm: boolean) => {
        applyTime(hour12, minutes, nextIsPm);
    };

    return (
        <Popover className={cn('relative', className)}>
            <PopoverButton
                id={id}
                aria-label={ariaLabel}
                aria-required={required}
                className={cn(
                    'border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full min-w-0 items-center justify-between rounded-md border px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden',
                    !value && 'text-muted-foreground',
                )}
            >
                <span dir="ltr" className="truncate tabular-nums">
                    {value ? formatTimeString12(value) : 'اختر الوقت'}
                </span>
                <Clock3 className="text-muted-foreground size-4 shrink-0" />
            </PopoverButton>

            <PopoverPanel
                anchor="bottom end"
                transition
                className="z-[100] w-[min(20rem,calc(100vw-2rem))] rounded-md border bg-popover p-3 text-popover-foreground shadow-md [--anchor-gap:6px] transition duration-150 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <div className="space-y-3">
                    <p className="text-muted-foreground text-center text-xs font-medium">اختر وقت بدء الدوام</p>

                    <div dir="ltr" className="flex items-center justify-center gap-3">
                        <TimeStepper
                            label="ساعة"
                            value={hour12}
                            onDecrease={() => adjustHours(-1)}
                            onIncrease={() => adjustHours(1)}
                        />
                        <span className="text-muted-foreground text-2xl font-semibold tabular-nums">:</span>
                        <TimeStepper
                            label="دقيقة"
                            value={minutes}
                            onDecrease={() => adjustMinutes(-1)}
                            onIncrease={() => adjustMinutes(1)}
                        />
                        <div className="flex flex-col items-center gap-1.5">
                            <Button
                                type="button"
                                variant={!isPm ? 'default' : 'outline'}
                                size="sm"
                                className="h-8 min-w-10 px-2 text-xs"
                                onClick={() => setPeriod(false)}
                            >
                                ص
                            </Button>
                            <Button
                                type="button"
                                variant={isPm ? 'default' : 'outline'}
                                size="sm"
                                className="h-8 min-w-10 px-2 text-xs"
                                onClick={() => setPeriod(true)}
                            >
                                م
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                        {presets.map((preset) => {
                            const presetParsed = parseTimeString(preset.value);
                            const isActive =
                                presetParsed !== null &&
                                presetParsed.hours === to24Hour(hour12, isPm) &&
                                presetParsed.minutes === minutes;

                            return (
                                <CloseButton
                                    key={preset.value}
                                    type="button"
                                    onClick={() => {
                                        if (presetParsed) {
                                            const preset12 = to12Hour(presetParsed.hours);
                                            applyTime(preset12.hour12, presetParsed.minutes, preset12.isPm);
                                        }
                                    }}
                                    className={cn(
                                        'hover:bg-accent hover:text-accent-foreground rounded-md px-2 py-1.5 text-xs tabular-nums transition-colors',
                                        isActive && 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                    )}
                                >
                                    <span dir="ltr">{preset.label}</span>
                                </CloseButton>
                            );
                        })}
                    </div>
                </div>
            </PopoverPanel>
        </Popover>
    );
}

function TimeStepper({
    label,
    value,
    onDecrease,
    onIncrease,
}: {
    label: string;
    value: number;
    onDecrease: () => void;
    onIncrease: () => void;
}) {
    return (
        <div className="flex flex-col items-center gap-1">
            <Button type="button" variant="ghost" size="icon" className="size-8" onClick={onIncrease}>
                <ChevronUp className="size-4" />
            </Button>
            <span dir="ltr" className="w-12 text-center text-2xl font-semibold tabular-nums">
                {String(value).padStart(2, '0')}
            </span>
            <Button type="button" variant="ghost" size="icon" className="size-8" onClick={onDecrease}>
                <ChevronDown className="size-4" />
            </Button>
            <span className="text-muted-foreground text-xs">{label}</span>
        </div>
    );
}
