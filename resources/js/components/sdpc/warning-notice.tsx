import { WarningIcon } from '@phosphor-icons/react';
import type { CSSProperties, ReactNode } from 'react';

/**
 * A red callout for something the user cannot do right now, such as applying
 * while they are already on a project. The look lives in `.notice-warning`
 * (nocturne.css), so every warning on the site reads the same.
 */
export default function WarningNotice({
    children,
    style,
}: {
    children: ReactNode;
    style?: CSSProperties;
}) {
    return (
        <div className="notice-warning" role="status" style={style}>
            <WarningIcon size={18} weight="fill" aria-hidden="true" />
            <span>{children}</span>
        </div>
    );
}
