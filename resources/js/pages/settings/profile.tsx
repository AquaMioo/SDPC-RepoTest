import { Head, Link, usePage } from '@inertiajs/react';
import { UserIcon } from '@phosphor-icons/react';

import AccountAppealCard from '@/components/account-appeal-card';
import type {
    AccountStatus,
    AppealState,
} from '@/components/account-appeal-card';
import DeleteUser from '@/components/delete-user';
import SignInMethodsCard from '@/components/sign-in-methods-card';
import type { SignInMethods } from '@/components/sign-in-methods-card';
import { edit } from '@/routes/profile';
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
 * address is confirmed, a student's sign-in methods, an appeal if there is a
 * decision to answer, and the way out.
 *
 * ProfileController::update and ProfileUpdateRequest are untouched — the
 * profile screen posts to them. Only the duplicate form is gone.
 */
export default function Profile({
    mustVerifyEmail,
    status,
    signInMethods,
    accountStatus,
    appeal,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    /** Students only: how they sign in, and the Google account for later. */
    signInMethods?: SignInMethods | null;
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
                            <Link href={send()} as="button" data-inline-link="">
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

            <SignInMethodsCard methods={signInMethods ?? null} />

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
