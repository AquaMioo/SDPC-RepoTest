import { Form, Head, Link, usePage } from '@inertiajs/react';
import { UserIcon } from '@phosphor-icons/react';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';
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
export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
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
                        {auth.user.avatar ? (
                            <img
                                src={auth.user.avatar}
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
