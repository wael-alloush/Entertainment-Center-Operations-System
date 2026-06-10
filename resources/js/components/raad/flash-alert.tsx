import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface FlashAlertProps {
    message?: string;
    variant?: 'success' | 'error';
}

export function FlashAlert({ message, variant = 'success' }: FlashAlertProps) {
    const [visible, setVisible] = useState(!!message);

    useEffect(() => {
        if (message) {
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [message]);

    if (!message || !visible) {
        return null;
    }

    const isError = variant === 'error';

    return (
        <Alert
            className={cn(
                isError ? 'border-destructive/30 bg-destructive/10' : 'border-emerald-500/30 bg-emerald-500/10',
            )}
        >
            {isError ? (
                <AlertCircle className="text-destructive size-5" />
            ) : (
                <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />
            )}
            <AlertDescription className={isError ? 'text-destructive' : 'text-emerald-800 dark:text-emerald-200'}>
                {message}
            </AlertDescription>
        </Alert>
    );
}
