import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, ComponentType, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * A password field with a show / hide eye inside it.
 *
 * `as` swaps the input underneath, so a screen drawn with the SDPC `.input`
 * styling (registration) gets the same toggle as the settings screens.
 */
export default function PasswordInput({
    className,
    ref,
    as: Field = Input,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & {
    ref?: Ref<HTMLInputElement>;
    as?: ComponentType<ComponentProps<'input'>>;
}) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            <Field
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-10', className)}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                tabIndex={-1}
            >
                {showPassword ? (
                    <EyeOff className="size-4" />
                ) : (
                    <Eye className="size-4" />
                )}
            </button>
        </div>
    );
}
