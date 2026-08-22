import { Form, Head, Link } from '@inertiajs/react';
import { EyeIcon, EyeSlashIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import GoogleAuthButton from '@/components/sdpc/google-auth-button';
import GoogleAuthError from '@/components/sdpc/google-auth-error';
import GoogleSetupHint from '@/components/sdpc/google-setup-hint';
import { Input } from '@/components/sdpc/input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Spinner } from '@/components/ui/spinner';
import { appeal, register } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canLoginWithGoogle?: boolean;
    googleSetupHint?: boolean;
    teamInvitation?: TeamInvitationContext | null;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/** One half of the hairline either side of the divider's label. */
const RULE: React.CSSProperties = {
    flex: 1,
    height: 1,
    background: 'var(--color-divider)',
};

export default function Login({
    status,
    canResetPassword,
    canLoginWithGoogle = false,
    googleSetupHint = false,
    teamInvitation,
}: Props) {
    const [revealed, setRevealed] = useState(false);

    return (
        <>
            <Head title="Log in" />

            {teamInvitation && (
                <TeamInvitationAlert
                    invitation={teamInvitation}
                    action="Log in"
                />
            )}

            <div
                className="card elev-md"
                style={{
                    width: 380,
                    padding: 28,
                    gap: 14,
                    position: 'relative',
                }}
            >
                <h4 style={{ margin: '0 0 4px', textAlign: 'center' }}>
                    Log in
                </h4>

                <GoogleAuthError />

                {status && (
                    <div
                        style={{
                            fontSize: 12.5,
                            textAlign: 'center',
                            color: 'var(--color-accent)',
                        }}
                    >
                        {status}
                    </div>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    style={{ display: 'contents' }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="field">
                                <label htmlFor="email">Email</label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="you@email.com"
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="password">Password</label>
                                <div style={{ position: 'relative' }}>
                                    <Input
                                        id="password"
                                        name="password"
                                        type={revealed ? 'text' : 'password'}
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        style={{ paddingRight: 34 }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setRevealed((v) => !v)}
                                        aria-label={
                                            revealed
                                                ? 'Hide password'
                                                : 'Show password'
                                        }
                                        style={{
                                            position: 'absolute',
                                            right: 10,
                                            top: 10,
                                            fontSize: 15,
                                            opacity: 0.55,
                                            cursor: 'pointer',
                                            background: 'none',
                                            border: 0,
                                            padding: 0,
                                            color: 'inherit',
                                        }}
                                    >
                                        {revealed ? (
                                            <EyeIcon />
                                        ) : (
                                            <EyeSlashIcon />
                                        )}
                                    </button>
                                </div>
                                <InputError
                                    message={errors.password}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            {/* Only drawn when there is something on the other
                                side of it — a rule with nothing below reads as
                                a missing section. */}
                            {canLoginWithGoogle && (
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        marginTop: 2,
                                    }}
                                >
                                    <span style={RULE} />
                                    <span
                                        style={{
                                            fontSize: 10.5,
                                            letterSpacing: '.12em',
                                            textTransform: 'uppercase',
                                            whiteSpace: 'nowrap',
                                            color: MUTED,
                                        }}
                                    >
                                        Or continue with email
                                    </span>
                                    <span style={RULE} />
                                </div>
                            )}

                            {canLoginWithGoogle && (
                                <GoogleAuthButton
                                    href={googleRedirect.url()}
                                    tabIndex={6}
                                />
                            )}
                            {googleSetupHint && <GoogleSetupHint />}

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    fontSize: 12,
                                    marginTop: 2,
                                }}
                            >
                                <label
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        alignItems: 'center',
                                        cursor: 'pointer',
                                        marginRight: 'auto',
                                    }}
                                >
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        tabIndex={3}
                                        style={{
                                            accentColor: 'var(--color-accent)',
                                            width: 15,
                                            height: 15,
                                        }}
                                    />
                                    Keep me logged in
                                </label>

                                {canResetPassword && (
                                    <Link
                                        href={request.url()}
                                        style={{ fontSize: 12 }}
                                        tabIndex={5}
                                    >
                                        Forgot password?
                                    </Link>
                                )}
                            </div>

                            <Btn
                                type="submit"
                                variant="primary"
                                block
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                                style={{ paddingBlock: 9 }}
                            >
                                {processing && <Spinner />}
                                Continue
                            </Btn>
                        </>
                    )}
                </Form>

                <div
                    style={{
                        textAlign: 'center',
                        fontSize: 12.5,
                        color: MUTED,
                    }}
                >
                    Don&apos;t have an SDPCC account?{' '}
                    <Btn asChild variant="ghost" style={{ fontSize: 12.5 }}>
                        <Link
                            href={register({
                                query: { invitation: teamInvitation?.code },
                            })}
                            data-test="register-link"
                            tabIndex={5}
                        >
                            Register
                        </Link>
                    </Btn>
                </div>

                {/* A deactivated account is turned away by AuthenticateUser
                    with a message and nowhere to go. This is where it goes. */}
                <div
                    style={{
                        textAlign: 'center',
                        fontSize: 11.5,
                        color: MUTED,
                    }}
                >
                    <Link href={appeal.url()} data-test="appeal-link">
                        Account restricted or deactivated? Appeal the decision
                    </Link>
                </div>
            </div>
        </>
    );
}
