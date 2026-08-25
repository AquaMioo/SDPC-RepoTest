import { Form, Head, Link, usePage } from '@inertiajs/react';
import { UserIcon } from '@phosphor-icons/react';

import AccountAppealCard, {
    type AccountStatus,
    type AppealState,
} from '@/components/account-appeal-card';
import DeleteUser from '@/components/delete-user';
import { Btn } from '@/components/sdpc/btn';
import { Tag } from '@/components/sdpc/tag';
import { edit } from '@/routes/profile';
import {
    returnMethod as verificationReturn,
    store as verificationStore,
} from '@/routes/student/verification';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const RULE: React.CSSProperties = {
    height: 1,
    background: 'var(--color-divider)',
};

/**
 * Account settings.
 *
 * Who you are is edited on your profile screen, not here. This page used to
 * carry a second editor for name, email and avatar, which meant two screens
 * claimed to own the same three fields and neither said which one won. What is
 * left is the account itself: which one you are signed in as, whether the
 * address is confirmed, the optional enrolment check, an appeal if there is a
 * decision to answer, and the way out.
 *
 * ProfileController::update and ProfileUpdateRequest are untouched — the
 * profile screen posts to them. Only the duplicate form is gone.
 */
type StudentVerification = {
    status: string;
    statusLabel: string;
    verifiedAt: string | null;
    failureReason: string | null;
    hasStarted: boolean;
};

export default function Profile({
    mustVerifyEmail,
    status,
    studentVerification,
    accountStatus,
    appeal,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    /** Null unless the optional third-party check is configured. */
    studentVerification?: StudentVerification | null;
    accountStatus: AccountStatus;
    /** The most recent appeal this account filed, if it filed one. */
    appeal: AppealState | null;
}) {
    const { auth } = usePage<PageProps>().props;

    const memberSince = new Date(auth.user.created_at).toLocaleDateString(
        undefined,
        { month: 'short', year: 'numeric' },
    );

    return (
        <>
            <Head title="Account settings" />

            <h4 style={{ margin: '0 0 16px' }}>Account settings</h4>

            <div className="card elev-sm" style={{ padding: 20, gap: 16 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <span
                        style={{
                            width: 56,
                            height: 56,
                            flex: 'none',
                            borderRadius: '50%',
                            background: 'var(--color-accent-800)',
                            display: 'grid',
                            placeItems: 'center',
                            color: 'var(--color-accent-200)',
                            fontSize: 26,
                            overflow: 'hidden',
                        }}
                    >
                        {auth.avatarUrl ? (
                            <img
                                src={auth.avatarUrl}
                                alt=""
                                style={{
                                    width: '100%',
                                    height: '100%',
                                    objectFit: 'cover',
                                }}
                            />
                        ) : (
                            <UserIcon />
                        )}
                    </span>

                    <div style={{ marginRight: 'auto' }}>
                        <div style={{ fontSize: 15 }}>{auth.user.name}</div>
                        <div style={{ fontSize: 11.5, color: MUTED(50) }}>
                            Member since {memberSince} · ID #{auth.user.id}
                        </div>
                    </div>
                </div>

                {mustVerifyEmail && auth.user.email_verified_at === null && (
                    <>
                        <div style={RULE} />

                        {/* Kept here rather than moved with the editor: this is
                            about the account being confirmed, not about
                            changing the address. */}
                        <div
                            style={{
                                fontSize: 12,
                                lineHeight: 1.55,
                                color: MUTED(60),
                            }}
                        >
                            Your email address is unverified.{' '}
                            <Link
                                href={send()}
                                as="button"
                                style={{
                                    color: 'var(--color-accent)',
                                    textDecoration: 'underline',
                                }}
                            >
                                Re-send the verification email.
                            </Link>
                            {status === 'verification-link-sent' && (
                                <div
                                    style={{
                                        marginTop: 6,
                                        color: 'var(--color-accent)',
                                    }}
                                >
                                    A new verification link has been sent to
                                    your email address.
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>

            {studentVerification && (
                <div
                    className="card elev-sm"
                    style={{ marginTop: 24, padding: 20, gap: 12 }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                        }}
                    >
                        <h6 style={{ margin: 0, marginRight: 'auto' }}>
                            Student verification
                        </h6>
                        <Tag
                            variant={
                                studentVerification.status === 'verified'
                                    ? 'accent'
                                    : 'neutral'
                            }
                        >
                            {studentVerification.statusLabel}
                        </Tag>
                    </div>

                    <p
                        style={{
                            margin: 0,
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            color: MUTED(60),
                        }}
                    >
                        Optional. A third party confirms you are enrolled and
                        your profile wears a verified badge. Nothing else
                        changes — applying, messaging and signing all still
                        answer to the credential document an administrator
                        reviews.
                    </p>

                    {studentVerification.verifiedAt && (
                        <span style={{ fontSize: 12, color: MUTED(60) }}>
                            Verified {studentVerification.verifiedAt}.
                        </span>
                    )}

                    {studentVerification.failureReason && (
                        <span style={{ fontSize: 12, color: MUTED(60) }}>
                            {studentVerification.failureReason}
                        </span>
                    )}

                    <div style={{ display: 'flex', gap: 10 }}>
                        <Form {...verificationStore.form()}>
                            <Btn type="submit" variant="secondary">
                                {studentVerification.hasStarted
                                    ? 'Start again'
                                    : 'Verify my student status'}
                            </Btn>
                        </Form>

                        {studentVerification.hasStarted &&
                            studentVerification.status !== 'verified' && (
                                <Btn asChild variant="ghost">
                                    <Link href={verificationReturn.url()}>
                                        Check for an answer
                                    </Link>
                                </Btn>
                            )}
                    </div>
                </div>
            )}

            <AccountAppealCard accountStatus={accountStatus} appeal={appeal} />

            <div style={{ marginTop: 24 }}>
                <DeleteUser />
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Account settings',
            href: edit(),
        },
    ],
};
