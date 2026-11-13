import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const PhoneInput = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
    (
        {
            className,
            type = 'tel',
            inputMode = 'numeric',
            pattern = '[0-9]*',
            autoComplete = 'tel-national',
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

PhoneInput.displayName = 'PhoneInput';

export { PhoneInput };
