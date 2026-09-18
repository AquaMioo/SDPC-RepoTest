import { Link, usePage } from '@inertiajs/react';

import { edit as profileEdit } from '@/routes/profile';

type SharedProps = {
    auth?: { status?: string | null };
};

/**
 * Says out loud that an account is being held back, and where to answer it.
 *
 * Monitored and deactivated accounts see this; pending is the ordinary state
 * of a new account. Without it, a monitored client clicking "Post a project"
 * would be bounced by EnsureAccountIsNotMonitored with no standing
 * explanation of why, and a deactivated one would only see a greyed-out
 * header.
 */
export default function AccountStatusBanner() {
    const { props } = usePage<SharedProps>();
    const status = props.auth?.status;

    if (status !== 'monitored' && status !== 'deactivated') {
        return null;
    }

    const deactivated = status === 'deactivated';

    return (
        <div
            role="status"
            data-test={deactivated ? 'deactivated-banner' : 'monitoring-banner'}
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
            {deactivated ? (
                <span style={{ marginRight: 'auto' }}>
                    <b>Your account has been deactivated.</b> Only Settings is
                    open until an administrator restores it. You can send an
                    appeal from Account.
                </span>
            ) : (
                <span style={{ marginRight: 'auto' }}>
                    <b>Your account is under review.</b> You can still look
                    around and talk to the people you are working with, but
                    posting, applying, hiring and signing are on hold.
                </span>
            )}

            <Link
                href={`${profileEdit.url()}#appeal`}
                style={{ textDecoration: 'underline' }}
            >
                Review appeal
            </Link>
        </div>
    );
}
