import { Link, usePage } from '@inertiajs/react';

import { edit as profileEdit } from '@/routes/profile';

type SharedProps = {
    auth?: { status?: string | null };
};

/**
 * Says out loud that an account is being held back, and where to answer it.
 *
 * Only monitored accounts see this: a deactivated one never gets far enough to
 * render a layout, and pending is the ordinary state of a new account. Without
 * it, a monitored client clicking "Post a project" would be bounced by
 * EnsureAccountIsNotMonitored with no standing explanation of why.
 */
export default function AccountStatusBanner() {
    const { props } = usePage<SharedProps>();

    if (props.auth?.status !== 'monitored') {
        return null;
    }

    return (
        <div
            role="status"
            data-test="monitoring-banner"
            className="page-shell"
            style={{
                maxWidth: 1180,
                margin: '16px auto 0',
                paddingBlock: 11,
                borderRadius: 'var(--radius-md)',
                background:
                    'color-mix(in srgb, var(--color-accent-2, var(--color-accent)) 14%, transparent)',
                border: '1px solid color-mix(in srgb, var(--color-text) 12%, transparent)',
                fontSize: 12.5,
                lineHeight: 1.6,
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                flexWrap: 'wrap',
            }}
        >
            <span style={{ marginRight: 'auto' }}>
                <b>Your account is under review.</b> You can still look around
                and talk to the people you are working with, but posting,
                applying, hiring and signing are on hold.
            </span>

            <Link
                href={profileEdit.url()}
                style={{ textDecoration: 'underline' }}
            >
                Review appeal
            </Link>
        </div>
    );
}
