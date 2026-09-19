import { Form, Head, Link, router } from '@inertiajs/react';
import {
    EnvelopeSimpleIcon,
    EyeIcon,
    EyeSlashIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import AccountSessionWarning from '@/components/sdpc/account-session-warning';
import AuthPitch, { Accent, wordmark } from '@/components/sdpc/auth-pitch';
import { Btn } from '@/components/sdpc/btn';
import EmailCodeEntry from '@/components/sdpc/email-code-entry';
import GoogleAuthButton from '@/components/sdpc/google-auth-button';
import GoogleAuthError from '@/components/sdpc/google-auth-error';
import GoogleSetupHint from '@/components/sdpc/google-setup-hint';
import { Input } from '@/components/sdpc/input';
import MicrosoftAuthButton from '@/components/sdpc/microsoft-auth-button';
import OAuthError from '@/components/sdpc/oauth-error';
import OAuthSetupHint from '@/components/sdpc/oauth-setup-hint';
import SchoolEmailField from '@/components/sdpc/school-email-field';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Spinner } from '@/components/ui/spinner';
import { useMod } from '@/hooks/use-mod';
import { appeal, register } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
import { code as sendLoginCode, store } from '@/routes/login';
import {
    cancel as cancelLoginCode,
    resend as resendLoginCode,
    verify as verifyLoginCode,
} from '@/routes/login/code';
import { redirect as microsoftRedirect } from '@/routes/microsoft';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

type Props = {
    status?: string;
    /** Why this device was signed out or refused — see AccountSession. */
    warning?: string | null;
    canResetPassword: boolean;
    canLoginWithGoogle?: boolean;
    googleSetupHint?: boolean;
    /** Students' school Microsoft 365 accounts. */
    canLoginWithMicrosoft?: boolean;
    microsoftSetupHint?: boolean;
    teamInvitation?: TeamInvitationContext | null;
    /** Counted, not claimed — see FortifyServiceProvider::configureViews(). */
    /**
     * A student part way through signing in with a school-email code. Said
     * the same way whether or not the address has an account; see
     * App\Support\PendingCodeLogin.
     */
    loginCode?: {
        email: string;
        codeLength: number;
        expiresAfter: number;
        secondsUntilResend: number;
    } | null;
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
    warning,
    canResetPassword,
    canLoginWithGoogle = false,
    googleSetupHint = false,
    canLoginWithMicrosoft = false,
    microsoftSetupHint = false,
    teamInvitation,
    loginCode,
}: Props) {
    const [revealed, setRevealed] = useState(false);

    /*
     * Students have no password (2026-09-20): they sign in with a code mailed
     * to their school address, or with a Google account bound in settings.
     * The card switches between that and the ordinary form rather than
     * showing both, and opens on the code while one is on its way.
     */
    const [withCode, setWithCode] = useState(Boolean(loginCode));
    const [codeEmail, setCodeEmail] = useState('');
    const [remember, setRemember] = useState(false);

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

                <AuthPitch
                    headline={
                        <>
                            Connecting tomorrow&apos;s developers with{' '}
                            <Accent>today&apos;s opportunities.</Accent>
                        </>
                    }
                    body="A collaborative platform for tertiary students and local clients in San Jose Del Monte — matched by skill, tracked to delivery."
                />

                <main className="relative flex min-h-screen flex-col items-center justify-center gap-5 px-6 py-12">
                    {/* Stands in for the one in the pitch panel, which is not
                        drawn at this width. */}
                    <Link href="/" className="lg:hidden" style={wordmark}>
                        SDPC
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
                        <OAuthError provider="microsoft" />

                        <AccountSessionWarning message={warning} />

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

                        {withCode ? (
                            loginCode ? (
                                <EmailCodeEntry
                                    email={loginCode.email}
                                    codeLength={loginCode.codeLength}
                                    expiresAfter={loginCode.expiresAfter}
                                    secondsUntilResend={
                                        loginCode.secondsUntilResend
                                    }
                                    submitUrl={verifyLoginCode.url()}
                                    resendUrl={resendLoginCode.url()}
                                    submitLabel="Log in"
                                    intro={
                                        /* Worded the same whether or not the
                                           address has an account. */
                                        <>
                                            If{' '}
                                            <b
                                                style={{
                                                    color: 'var(--color-text)',
                                                }}
                                            >
                                                {loginCode.email}
                                            </b>{' '}
                                            belongs to a student account, we
                                            sent a {loginCode.codeLength}-digit
                                            code to it. It expires in{' '}
                                            {loginCode.expiresAfter} minutes.
                                        </>
                                    }
                                    extraData={() => ({ remember })}
                                    onStartOver={() =>
                                        router.delete(cancelLoginCode.url())
                                    }
                                    startOverLabel="Use a different email"
                                >
                                    <label
                                        style={{
                                            display: 'flex',
                                            gap: 8,
                                            alignItems: 'center',
                                            cursor: 'pointer',
                                            fontSize: 12,
                                            alignSelf: 'flex-start',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={remember}
                                            onChange={(event) =>
                                                setRemember(
                                                    event.target.checked,
                                                )
                                            }
                                            style={{
                                                accentColor:
                                                    'var(--color-accent)',
                                                width: 15,
                                                height: 15,
                                            }}
                                        />
                                        Keep me logged in
                                    </label>
                                </EmailCodeEntry>
                            ) : (
                                <Form
                                    {...sendLoginCode.form()}
                                    disableWhileProcessing
                                    style={{ display: 'contents' }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <p
                                                style={{
                                                    margin: 0,
                                                    fontSize: 12.5,
                                                    lineHeight: 1.6,
                                                    color: MUTED,
                                                    textAlign: 'center',
                                                }}
                                            >
                                                Students: enter your school
                                                email and we&apos;ll send you a
                                                code. No password needed.
                                            </p>

                                            <SchoolEmailField
                                                id="login_school_email"
                                                name="email"
                                                value={codeEmail}
                                                onChange={setCodeEmail}
                                                error={errors.email}
                                                tabIndex={1}
                                                autoFocus
                                            />

                                            <Btn
                                                type="submit"
                                                variant="primary"
                                                block
                                                tabIndex={2}
                                                data-test="send-login-code"
                                                style={{ paddingBlock: 9 }}
                                            >
                                                {processing && <Spinner />}
                                                Email me a code
                                            </Btn>

                                            <Btn
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    setWithCode(false)
                                                }
                                                style={{
                                                    fontSize: 12.5,
                                                    alignSelf: 'center',
                                                }}
                                                data-test="login-with-password"
                                            >
                                                Log in with a password instead
                                            </Btn>
                                        </>
                                    )}
                                </Form>
                            )
                        ) : (
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
                                            <div
                                                style={{ position: 'relative' }}
                                            >
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

                                        {/*
                                         * Students who signed up with their school
                                         * Microsoft account have no password, so
                                         * this is their way in. A student whose
                                         * school address has closed signs in with
                                         * the Google account bound in settings.
                                         */}
                                        {canLoginWithMicrosoft && (
                                            <MicrosoftAuthButton
                                                href={microsoftRedirect.url()}
                                                tabIndex={6}
                                            />
                                        )}
                                        {microsoftSetupHint && (
                                            <OAuthSetupHint
                                                provider="Microsoft"
                                                variables={[
                                                    'MICROSOFT_CLIENT_ID',
                                                    'MICROSOFT_CLIENT_SECRET',
                                                ]}
                                            />
                                        )}

                                        {/* Students have no password: this is
                                        their way in without a bound Google
                                        account. */}
                                        <Btn
                                            type="button"
                                            variant="secondary"
                                            block
                                            tabIndex={6}
                                            onClick={() => setWithCode(true)}
                                            data-test="login-with-code"
                                            style={{ paddingBlock: 9 }}
                                        >
                                            <EnvelopeSimpleIcon />
                                            Log in with a school email code
                                        </Btn>

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
                        )}

                        <div
                            style={{
                                textAlign: 'center',
                                fontSize: 12.5,
                                color: MUTED,
                            }}
                        >
                            Don&apos;t have an SDPC account?{' '}
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

                        {/* A restricted or deactivated account can sign in and
                            appeal from Settings. This is the door for anyone
                            who cannot sign in at all — a forgotten password, a
                            closed school mailbox. */}
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
