import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const NumericInput = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
    (
        {
            className,
            type = 'text',
            inputMode = 'numeric',
            pattern = '[0-9]*',
            autoComplete = 'off',
            dir = 'ltr',
            ...props
        },
        ref,
    ) => (
        <Input
            ref={ref}
            type={type}
            inputMode={inputMode}
            pattern={pattern}
            autoComplete={autoComplete}
            dir={dir}
            className={cn(className)}
            {...props}
        />
    ),
);

NumericInput.displayName = 'NumericInput';

export { NumericInput };
