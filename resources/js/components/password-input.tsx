/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The eye button that shows or hides a password. It sits at the end of the field
 * (right in ru/en, left in he/ar) and keeps the cursor in the field when pressed.
 */
export function PasswordToggle({ shown, onToggle, className }: { shown: boolean; onToggle: () => void; className?: string }) {
    const t = useT();
    const label = shown ? t('Hide password') : t('Show password');

    return (
        <button
            type="button"
            onClick={onToggle}
            onMouseDown={(e) => e.preventDefault()}
            aria-label={label}
            aria-pressed={shown}
            title={label}
            className={cn(
                'text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-hidden',
                className,
            )}
        >
            {shown ? <EyeOff className="size-4" aria-hidden /> : <Eye className="size-4" aria-hidden />}
        </button>
    );
}

/** A password field with the eye button: the same props as Input, without type. className goes on the wrapper. */
const PasswordInput = React.forwardRef<HTMLInputElement, Omit<React.ComponentProps<'input'>, 'type'>>(({ className, ...props }, ref) => {
    const [shown, setShown] = React.useState(false);

    return (
        <div className={cn('relative', className)}>
            <Input ref={ref} type={shown ? 'text' : 'password'} className="pe-10" {...props} />
            <PasswordToggle shown={shown} onToggle={() => setShown((s) => !s)} className="absolute end-1 top-1/2 -translate-y-1/2" />
        </div>
    );
});

PasswordInput.displayName = 'PasswordInput';

export { PasswordInput };
