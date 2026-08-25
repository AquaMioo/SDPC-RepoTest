import { Form, Head, Link } from '@inertiajs/react';
import { CheckCircleIcon, EyeIcon, EyeSlashIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import GoogleAuthButton from '@/components/sdpc/google-auth-button';
import GoogleAuthError from '@/components/sdpc/google-auth-error';
import GoogleSetupHint from '@/components/sdpc/google-setup-hint';
import { Input } from '@/components/sdpc/input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Spinner } from '@/components/ui/spinner';
import { useMod } from '@/hooks/use-mod';
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
    /** Counted, not claimed — see FortifyServiceProvider::configureViews(). */
    projectsDelivered?: number;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/**
 * Log in — the one auth screen that carries the pitch beside the form.
 *
 * It brings its own shell rather than wearing AuthLayout's centred column, the
 * same way admin/login does, because the split is the design: signing in is
 * also the page a first-time visitor lands on from the front door, so the left
 * half says what the platform is while the right half lets you in. Every other
 * auth screen — register, appeal, the code step — keeps the centred card,
 * since by then you already know why you are here.
 *
 * Registered as its own layout in app.tsx (`case name === 'auth/login'`).
 */
export default function Login({
    status,
    canResetPassword,
    canLoginWithGoogle = false,
    googleSetupHint = false,
    teamInvitation,
    projectsDelivered = 0,
}: Props) {
    const [revealed, setRevealed] = useState(false);

    useMod('user');

    return (
        <>
            <Head title="Log in" />

            <div
                data-mod="user"
                className="relative min-h-screen overflow-hidden lg:grid lg:grid-cols-[1.12fr_1fr]"
                style={{
                    background: 'var(--color-bg)',
                    color: 'var(--color-text)',
                    fontFamily: 'var(--font-body)',
                }}
            >
                {/* The accent glow the design bleeds in from off-screen. */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute"
                    style={{
                        top: -220,
                        left: -140,
                        width: 780,
                        height: 480,
                        background:
                            'radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--color-accent) 22%, transparent), transparent 70%)',
                        filter: 'blur(40px)',
                    }}
                />

                {/*
                 * The pitch. Hidden below `lg` rather than stacked above the
                 * form: on a phone the first thing wanted is the password
                 * field, not the tagline.
                 */}
                <aside
                    className="relative hidden flex-col justify-between p-12 lg:flex xl:p-16"
                    style={{ borderRight: '1px solid var(--color-divider)' }}
                >
                    <Link href="/" style={wordmark}>
                        SDPCC
                    </Link>

                    <div style={{ maxWidth: 460 }}>
                        <h1
                            style={{
                                margin: 0,
                                fontFamily: 'var(--font-heading)',
                                fontWeight: 'var(--font-heading-weight)',
                                fontSize: 'clamp(32px, 3.2vw, 44px)',
                                lineHeight: 1.14,
                                letterSpacing: '-0.025em',
                            }}
                        >
                            Connecting tomorrow&apos;s developers with{' '}
                            <span style={{ color: 'var(--color-accent)' }}>
                                today&apos;s opportunities.
                            </span>
                        </h1>

                        <p
                            style={{
                                margin: '18px 0 0',
                                maxWidth: 330,
                                fontSize: 13,
                                lineHeight: 1.65,
                                color: MUTED,
                            }}
                        >
                            A collaborative platform for tertiary students and
                            local clients in San Jose Del Monte — matched by
                            skill, tracked to delivery.
                        </p>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: '10px 26px',
                        }}
                    >
                        <Proof>Verified students and businesses</Proof>
                        {/*
                         * Only shown once there is something to show. "0
                         * projects delivered" is a worse first impression than
                         * no claim at all, and the number is counted rather
                         * than written down.
                         */}
                        {projectsDelivered > 0 && (
                            <Proof>
                                {projectsDelivered.toLocaleString()} project
                                {projectsDelivered === 1 ? '' : 's'} delivered
                            </Proof>
                        )}
                    </div>
                </aside>

                <main className="relative flex min-h-screen flex-col items-center justify-center gap-5 px-6 py-12">
                    {/* Stands in for the one in the pitch panel, which is not
                        drawn at this width. */}
                    <Link href="/" className="lg:hidden" style={wordmark}>
                        SDPCC
                    </Link>

                    {teamInvitation && (
                        <TeamInvitationAlert
                            invitation={teamInvitation}
                            action="Log in"
                        />
                    )}

                    <div
                        className="card elev-md"
                        style={{
                            width: '100%',
                            maxWidth: 380,
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
                                        <label htmlFor="password">
                                            Password
                                        </label>
                                        <div style={{ position: 'relative' }}>
                                            <Input
                                                id="password"
                                                name="password"
                                                type={
                                                    revealed
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder="••••••••"
                                                style={{ paddingRight: 34 }}
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setRevealed((v) => !v)
                                                }
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
                                                    accentColor:
                                                        'var(--color-accent)',
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
                            <Btn
                                asChild
                                variant="ghost"
                                style={{ fontSize: 12.5 }}
                            >
                                <Link
                                    href={register({
                                        query: {
                                            invitation: teamInvitation?.code,
                                        },
                                    })}
                                    data-test="register-link"
                                    tabIndex={5}
                                >
                                    Register
                                </Link>
                            </Btn>
                        </div>

                        {/* A deactivated account is turned away by
                            AuthenticateUser with a message and nowhere to go.
                            This is where it goes. */}
                        <div
                            style={{
                                textAlign: 'center',
                                fontSize: 11.5,
                                color: MUTED,
                            }}
                        >
                            <Link href={appeal.url()} data-test="appeal-link">
                                Account restricted or deactivated? Appeal the
                                decision
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}

/** The wordmark, in both the pitch panel and its small-screen stand-in. */
const wordmark: React.CSSProperties = {
    fontFamily: 'var(--font-heading)',
    fontWeight: 600,
    fontSize: 22,
    letterSpacing: '-0.02em',
    color: 'var(--color-accent)',
    textDecoration: 'none',
};

/** One ticked line of social proof along the foot of the pitch panel. */
function Proof({ children }: { children: React.ReactNode }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 7,
                fontSize: 11.5,
                color: MUTED,
            }}
        >
            <CheckCircleIcon
                size={14}
                weight="fill"
                style={{ color: 'var(--color-accent)', flex: 'none' }}
            />
            {children}
        </span>
    );
}
