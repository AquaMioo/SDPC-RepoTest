import { useInitials } from '@/hooks/use-initials';

/**
 * One account's picture, wherever it is drawn.
 *
 * Every avatar on the platform goes through here, and every one of them is fed
 * from `User::avatarUrl()` on the server — the uploaded file if there is one,
 * otherwise whatever the OAuth provider last handed over. Do not draw an
 * avatar from `users.avatar` directly: that column holds only the provider's
 * URL, so a person who uploaded a picture kept showing their old Google one
 * on any screen that read it.
 *
 * Falls back to initials rather than a stock silhouette, so an account with no
 * picture still reads as that particular person.
 */
export default function UserAvatar({
    name,
    avatarUrl = null,
    size = 32,
    title,
}: {
    name: string;
    /** Resolved by the server. Null means this account has no picture. */
    avatarUrl?: string | null;
    size?: number;
    title?: string;
}) {
    const getInitials = useInitials();

    const shared: React.CSSProperties = {
        width: size,
        height: size,
        flex: 'none',
        borderRadius: '999px',
        display: 'grid',
        placeItems: 'center',
        overflow: 'hidden',
        userSelect: 'none',
    };

    if (avatarUrl !== null && avatarUrl !== '') {
        return (
            <img
                src={avatarUrl}
                alt=""
                title={title ?? name}
                style={{ ...shared, objectFit: 'cover' }}
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            title={title ?? name}
            style={{
                ...shared,
                background:
                    'color-mix(in srgb, var(--color-accent) 22%, transparent)',
                color: 'var(--color-text)',
                fontSize: Math.max(9, Math.round(size * 0.38)),
                fontWeight: 600,
                lineHeight: 1,
            }}
        >
            {getInitials(name)}
        </span>
    );
}
