import { Form, Head, Link, usePage } from '@inertiajs/react';
import { UserIcon } from '@phosphor-icons/react';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Tag } from '@/components/sdpc/tag';
import { Spinner } from '@/components/ui/spinner';
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

const TWO_UP: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: 14,
};

const RULE: React.CSSProperties = {
    height: 1,
    background: 'var(--color-divider)',
};

/**
 * Account settings.
 *
 * Only name and email are editable — those are what ProfileUpdateRequest
 * accepts. The design also sketches a username, phone, address, school and
 * bio, but none of them have a column to live in, so they are left out rather
 * than rendered as inputs that quietly discard what is typed into them.
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
}: {
    mustVerifyEmail: boolean;
    status?: string;
    /** Null unless the optional third-party check is configured. */
    studentVerification?: StudentVerification | null;
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

                <div style={RULE} />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    style={{ display: 'contents' }}
                >
                    {({ processing, errors, reset }) => (
                        <>
                            <div style={TWO_UP}>
                                <div className="field">
                                    <label htmlFor="name">Full name</label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        defaultValue={auth.user.name}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError
                                        message={errors.name}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>

                                <div className="field">
                                    <label htmlFor="email">Email address</label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="username"
                                        defaultValue={auth.user.email}
                                        aria-invalid={Boolean(errors.email)}
                                    />
                                    <InputError
                                        message={errors.email}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>
                            </div>

                            <div className="field">
                                <label htmlFor="avatar">Profile picture</label>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 48,
                                            height: 48,
                                            borderRadius: '50%',
                                            overflow: 'hidden',
                                            flex: 'none',
                                            display: 'grid',
                                            placeItems: 'center',
                                            background:
                                                'var(--color-accent-800)',
                                            color: 'var(--color-accent-200)',
                                            fontSize: 11,
                                        }}
                                    >
                                        {auth.avatarUrl ? (
                                            <img
                                                src={auth.avatarUrl}
                                                alt={auth.user.name}
                                                style={{
                                                    width: '100%',
                                                    height: '100%',
                                                    objectFit: 'cover',
                                                }}
                                            />
                                        ) : (
                                            'None'
                                        )}
                                    </span>
                                    <Input
                                        id="avatar"
                                        name="avatar"
                                        type="file"
                                        accept="image/*"
                                        aria-invalid={Boolean(errors.avatar)}
                                    />
                                </div>
                                <InputError
                                    message={errors.avatar}
                                    className="mt-1 text-[11px]"
                                />
                                <p
                                    style={{
                                        margin: '6px 0 0',
                                        fontSize: 11.5,
                                        color: MUTED(60),
                                    }}
                                >
                                    Shown to clients, students and
                                    administrators wherever your account
                                    appears. PNG or JPG, up to 100 MB.
                                </p>
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
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
                                        {status ===
                                            'verification-link-sent' && (
                                            <div
                                                style={{
                                                    marginTop: 6,
                                                    color: 'var(--color-accent)',
                                                }}
                                            >
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                }}
                            >
                                <span
                                    style={{
                                        fontSize: 11.5,
                                        color: MUTED(45),
                                        marginRight: 'auto',
                                    }}
                                >
                                    Changes to your name are reviewed before
                                    they appear publicly.
                                </span>

                                <Btn
                                    type="button"
                                    variant="secondary"
                                    onClick={() => reset()}
                                >
                                    Discard
                                </Btn>

                                <Btn
                                    type="submit"
                                    variant="primary"
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    Save changes
                                </Btn>
                            </div>
                        </>
                    )}
                </Form>
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
