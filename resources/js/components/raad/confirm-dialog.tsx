import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { AlertTriangle } from 'lucide-react';
import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from 'react';

export type ConfirmOptions = {
    title?: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
};

type ConfirmState = ConfirmOptions & { open: true };

const ConfirmDialogContext = createContext<((options: ConfirmOptions | string) => Promise<boolean>) | null>(null);

export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [state, setState] = useState<ConfirmState | null>(null);
    const resolveRef = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback((options: ConfirmOptions | string): Promise<boolean> => {
        const normalized: ConfirmOptions = typeof options === 'string' ? { description: options } : options;
        const variant = normalized.variant ?? 'default';

        return new Promise((resolve) => {
            resolveRef.current = resolve;
            setState({
                title: normalized.title ?? 'تأكيد',
                description: normalized.description,
                confirmLabel: normalized.confirmLabel ?? (variant === 'destructive' ? 'تأكيد' : 'موافق'),
                cancelLabel: normalized.cancelLabel ?? 'إلغاء',
                variant,
                open: true,
            });
        });
    }, []);

    const finish = (result: boolean) => {
        resolveRef.current?.(result);
        resolveRef.current = null;
        setState(null);
    };

    const isDestructive = state?.variant === 'destructive';

    return (
        <ConfirmDialogContext.Provider value={confirm}>
            {children}
            <Dialog
                open={state !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        finish(false);
                    }
                }}
            >
                {state && (
                    <DialogContent className="gap-0 overflow-hidden p-0 md:max-w-md [&>button.absolute]:hidden" dir="rtl">
                        <div className="flex gap-3 p-4 pb-3">
                            <div
                                className={cn(
                                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                    isDestructive ? 'bg-destructive/12 text-destructive' : 'bg-primary/12 text-primary',
                                )}
                            >
                                <AlertTriangle className="size-5" strokeWidth={2.25} />
                            </div>
                            <DialogHeader className="space-y-2 text-right">
                                <DialogTitle className="text-base font-bold">{state.title}</DialogTitle>
                                <DialogDescription className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                                    {state.description}
                                </DialogDescription>
                            </DialogHeader>
                        </div>
                        <DialogFooter className="flex-col-reverse gap-2 border-t border-border/60 bg-muted/20 px-4 py-3 sm:flex-row sm:justify-start">
                            <Button
                                type="button"
                                className="min-h-11 w-full sm:w-auto"
                                variant={isDestructive ? 'destructive' : 'default'}
                                onClick={() => finish(true)}
                            >
                                {state.confirmLabel}
                            </Button>
                            <Button type="button" className="min-h-11 w-full sm:w-auto" variant="outline" onClick={() => finish(false)}>
                                {state.cancelLabel}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </ConfirmDialogContext.Provider>
    );
}

export function useConfirm(): (options: ConfirmOptions | string) => Promise<boolean> {
    const context = useContext(ConfirmDialogContext);

    if (context === null) {
        throw new Error('useConfirm must be used within ConfirmDialogProvider');
    }

    return context;
}
