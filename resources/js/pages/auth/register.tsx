import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
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
import RoleTransition, {
    useRoleTransition,
} from '@/components/sdpc/role-transition';
import SchoolEmailField from '@/components/sdpc/school-email-field';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Spinner } from '@/components/ui/spinner';
import { legal, login } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
import { redirect as microsoftRedirect } from '@/routes/microsoft';
import { schoolEmail as sendSchoolEmailCode, store } from '@/routes/register';
import { forget as forgetIdentity } from '@/routes/register/identity';
import {
    resend as resendSchoolEmailCode,
    verify as verifySchoolEmailCode,
} from '@/routes/register/school-email';
import type { TeamInvitationContext } from '@/types';

type Role = { value: string; label: string };

/** Set once someone has come back from Google without an account yet. */
type GoogleProfile = {
    email: string;
    first_name: string;
    last_name: string;
    avatar: string | null;
};

/** Set once a student has come back from their school Microsoft account. */
type MicrosoftProfile = {
    email: string;
    first_name: string;
    last_name: string;
};

/** A school address with a code on its way to it, on the Student tab. */
type SchoolEmailCode = {
    email: string;
    codeLength: number;
    expiresAfter: number;
    secondsUntilResend: number;
};

/** Set once the student has typed the code back: the address is proved. */
type SchoolEmailProfile = {
    email: string;
};

type Props = {
    passwordRules: string;
    roles?: Role[];
    canLoginWithGoogle?: boolean;
    googleSetupHint?: boolean;
    canLoginWithMicrosoft?: boolean;
    microsoftSetupHint?: boolean;
    teamInvitation?: TeamInvitationContext | null;
    googleProfile?: GoogleProfile | null;
    microsoftProfile?: MicrosoftProfile | null;
    schoolEmailCode?: SchoolEmailCode | null;
    schoolEmailProfile?: SchoolEmailProfile | null;
    /** Domains schools actually issue addresses on. */
    schoolDomains?: string[];
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/*
 * Last/First and Password/Confirm sit side by side, which is the design.
 *
 * The floor is 150 and not 200: the card is 420 wide with 28 of padding, so
 * two 200px tracks plus the gap cannot fit in 364 and auto-fit silently
 * collapsed both pairs into a single stacked column.
 */
const TWO_UP: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
    gap: 10,
};

/**
 * Register. The role segmented control is the real thing, not a preview toggle:
 * it drives which extra field is asked for and is submitted as `role`, which
 * CreateNewUser validates against the self-registerable roles before assigning
 * it. Admin is never offered.
 */
export default function Register({
    passwordRules,
    roles = [
        { value: 'client', label: 'Client' },
        { value: 'student', label: 'Student' },
    ],
    canLoginWithGoogle = false,
    googleSetupHint = false,
    canLoginWithMicrosoft = false,
    microsoftSetupHint = false,
    teamInvitation,
    googleProfile,
    microsoftProfile,
    schoolEmailCode,
    schoolEmailProfile,
    schoolDomains = [],
}: Props) {
    /*
     * An identity waiting on the server decides the role: a Google address is
     * not a school address, so it can only become a client, while a school
     * Microsoft account — or a school address with a code on its way or
     * already typed back — can only become a student. The server enforces the
     * same thing; this just keeps the other option from being picked.
     */
    const lockedRole =
        microsoftProfile || schoolEmailProfile || schoolEmailCode
            ? 'student'
            : googleProfile
              ? 'client'
              : null;
    const pendingIdentity = microsoftProfile
        ? { provider: 'microsoft', email: microsoftProfile.email }
        : schoolEmailProfile
          ? { provider: 'school-email', email: schoolEmailProfile.email }
          : googleProfile
            ? { provider: 'google', email: googleProfile.email }
            : null;
    const prefill = microsoftProfile ?? googleProfile;

    const [role, setRole] = useState<string>(
        lockedRole ?? roles[0]?.value ?? 'client',
    );
    const isStudent = role === 'student';

    /*
     * The Student tab asks for the school address and its code before the
     * rest of the form, the way the Client tab settles Google first. Until
     * one of those has proved an address there is nothing else to fill in.
     */
    const needsSchoolEmailStep =
        isStudent && !microsoftProfile && !schoolEmailProfile;

    /*
     * By the time a student sees the full form the address is proved, so it
     * is read from the identity rather than kept in state: the page stays
     * mounted across the code step, and state would still hold what it
     * started with.
     */
    const provedSchoolEmail =
        microsoftProfile?.email ?? schoolEmailProfile?.email ?? '';

    /*
     * The role picker sweeps the screen rather than swapping in place: the two
     * roles are different pitches on opposite sides of the page, and changing
     * one under somebody mid-read is what the shutter exists to avoid. The
     * change lands while the panels cover it.
     *
     * Direction comes from the order of `roles`, so Client to Student reads as
     * forward and Student to Client as back.
     */
    const { phase, direction, go, busy } = useRoleTransition(setRole, {
        /* The page arrives from behind the same sweep the role picker uses. */
        entrance: true,
    });

    const changeRole = (next: string) => {
        if (next === role || busy || lockedRole) {
            return;
        }

        const indexOf = (value: string) =>
            roles.findIndex((option) => option.value === value);

        go(next, indexOf(next) > indexOf(role));
    };

    const pitch = isStudent
        ? {
              headline: (
                  <>
                      Turn your capstone into a reality by connecting with{' '}
                      <Accent>local clients.</Accent>
                  </>
              ),
              body: 'Build for a real business in San Jose Del Monte, document the work, and graduate with a system in production.',
          }
        : {
              headline: (
                  <>
                      Partner with local IT students to help them achieve their{' '}
                      <Accent>capstone goals.</Accent>
                  </>
              ),
              body: 'Post the system your business actually needs and let matching bring you the students equipped to build it.',
          };

    return (
        <>
            <Head title="Register" />

            <div
                data-mod="user"
                className={
                    'relative min-h-screen overflow-hidden lg:grid ' +
                    (isStudent
                        ? 'lg:grid-cols-[1fr_1.12fr]'
                        : 'lg:grid-cols-[1.12fr_1fr]')
                }
                style={{
                    background: 'var(--color-bg)',
                    color: 'var(--color-text)',
                    fontFamily: 'var(--font-body)',
                }}
            >
                <RoleTransition phase={phase} direction={direction} />

                {/*
                 * The accent glow the design bleeds in from off-screen, on
                 * whichever edge the pitch is against.
                 */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute"
                    style={{
                        top: -220,
                        [isStudent ? 'right' : 'left']: -140,
                        width: 780,
                        height: 480,
                        background:
                            'radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--color-accent) 22%, transparent), transparent 70%)',
                        filter: 'blur(40px)',
                    }}
                />

                {/*
                 * The pitch swaps sides with the role, so the segmented
                 * control moves the whole page rather than only the fields —
                 * the two audiences are being addressed, not toggled between.
                 * Order rather than markup order, so the form stays first in
                 * the DOM for a screen reader either way.
                 */}
                <AuthPitch
                    headline={pitch.headline}
                    body={pitch.body}
                    side={isStudent ? 'right' : 'left'}
                    className={isStudent ? 'lg:order-2' : 'lg:order-1'}
                    phase={phase}
                />

                <main
                    className={
                        'relative flex min-h-screen flex-col items-center justify-center gap-5 px-6 py-12 ' +
                        (isStudent ? 'lg:order-1' : 'lg:order-2')
                    }
                >
                    {/* Stands in for the one in the pitch panel, which is not
                        drawn at this width. */}
                    <Link href="/" className="lg:hidden" style={wordmark}>
                        SDPC
                    </Link>

                    <div
                        className="card elev-md"
                        style={{
                            width: '100%',
                            maxWidth: 420,
                            padding: 28,
                            gap: 13,
                            position: 'relative',
                        }}
                    >
                        <h4 style={{ margin: 0, textAlign: 'center' }}>
                            Sign up
                        </h4>

                        <GoogleAuthError />
                        <OAuthError provider="microsoft" />

                        {pendingIdentity && (
                            <div
                                data-test={`${pendingIdentity.provider}-continuing-as`}
                                className="border border-green-600/40 bg-green-600/10 px-3 py-2 text-center dark:border-green-400/40 dark:bg-green-400/10"
                                style={{
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: 12,
                                    lineHeight: 1.55,
                                    color: MUTED,
                                }}
                            >
                                Continuing as{' '}
                                <b style={{ color: 'var(--color-text)' }}>
                                    {pendingIdentity.email}
                                </b>
                                . Finish the details below — no password needed.{' '}
                                <Link
                                    href={forgetIdentity.url()}
                                    method="delete"
                                    as="button"
                                    data-test="forget-identity"
                                    data-inline-link=""
                                    style={{
                                        border: 0,
                                        padding: 0,
                                        cursor: 'pointer',
                                        font: 'inherit',
                                    }}
                                >
                                    Not you? Start over
                                </Link>
                            </div>
                        )}

                        {teamInvitation && (
                            <TeamInvitationAlert
                                invitation={teamInvitation}
                                action="Register"
                            />
                        )}

                        <div
                            className="seg seg-pill"
                            style={{ alignSelf: 'center' }}
                        >
                            {roles.map((option) => {
                                const unavailable =
                                    lockedRole !== null &&
                                    option.value !== lockedRole;

                                return (
                                    <label
                                        className="seg-opt"
                                        key={option.value}
                                        style={
                                            unavailable
                                                ? {
                                                      opacity: 0.45,
                                                      cursor: 'not-allowed',
                                                  }
                                                : undefined
                                        }
                                    >
                                        <input
                                            type="radio"
                                            name="role-picker"
                                            value={option.value}
                                            checked={role === option.value}
                                            disabled={unavailable}
                                            onChange={() =>
                                                changeRole(option.value)
                                            }
                                        />
                                        {option.label}
                                    </label>
                                );
                            })}
                        </div>

                        {needsSchoolEmailStep ? (
                            <SchoolEmailStep
                                schoolDomains={schoolDomains}
                                code={schoolEmailCode ?? null}
                                alternative={
                                    <>
                                        {canLoginWithMicrosoft && (
                                            <MicrosoftAuthButton
                                                href={microsoftRedirect.url({
                                                    query: {
                                                        intent: 'register',
                                                    },
                                                })}
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
                                    </>
                                }
                            />
                        ) : (
                            <Form
                                {...store.form()}
                                resetOnSuccess={[
                                    'password',
                                    'password_confirmation',
                                ]}
                                disableWhileProcessing
                                style={{ display: 'contents' }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        {/* The segmented control lives outside the form so it
                                can drive conditional fields; its value rides
                                along here. */}
                                        <input
                                            type="hidden"
                                            name="role"
                                            value={role}
                                        />
                                        <InputError
                                            message={errors.role}
                                            className="text-center text-[11px]"
                                        />

                                        <div style={TWO_UP}>
                                            <div className="field">
                                                <label htmlFor="last_name">
                                                    Last name
                                                </label>
                                                <Input
                                                    id="last_name"
                                                    name="last_name"
                                                    required
                                                    tabIndex={1}
                                                    autoComplete="family-name"
                                                    placeholder="Clemens"
                                                    defaultValue={
                                                        prefill?.last_name
                                                    }
                                                />
                                                <InputError
                                                    message={errors.last_name}
                                                    className="mt-1 text-[11px]"
                                                />
                                            </div>

                                            <div className="field">
                                                <label htmlFor="first_name">
                                                    First name
                                                </label>
                                                <Input
                                                    id="first_name"
                                                    name="first_name"
                                                    required
                                                    autoFocus
                                                    tabIndex={2}
                                                    autoComplete="given-name"
                                                    placeholder="Samuel"
                                                    defaultValue={
                                                        prefill?.first_name
                                                    }
                                                />
                                                <InputError
                                                    message={errors.first_name}
                                                    className="mt-1 text-[11px]"
                                                />
                                            </div>
                                        </div>

                                        {/*
                                         * Students have no separate email: the
                                         * school address below is the one they
                                         * sign in with.
                                         */}
                                        {!isStudent && (
                                            <div className="field">
                                                <label htmlFor="email">
                                                    Email
                                                </label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required={!googleProfile}
                                                    tabIndex={3}
                                                    autoComplete="email"
                                                    placeholder="you@email.com"
                                                    // Google vouched for this address, so it is
                                                    // shown but not editable. The server reads
                                                    // it from the session either way.
                                                    defaultValue={
                                                        googleProfile?.email
                                                    }
                                                    readOnly={Boolean(
                                                        googleProfile,
                                                    )}
                                                    style={
                                                        googleProfile
                                                            ? {
                                                                  opacity: 0.75,
                                                                  cursor: 'not-allowed',
                                                              }
                                                            : undefined
                                                    }
                                                />
                                                <InputError
                                                    message={errors.email}
                                                    className="mt-1 text-[11px]"
                                                />
                                            </div>
                                        )}

                                        {isStudent ? (
                                            /*
                                             * Microsoft or the code has proved
                                             * this address, so it is shown but
                                             * not editable. The server reads it
                                             * from the session.
                                             */
                                            <SchoolEmailField
                                                value={provedSchoolEmail}
                                                onChange={() => {}}
                                                schoolDomains={schoolDomains}
                                                error={errors.school_email}
                                                readOnly
                                                tabIndex={4}
                                            />
                                        ) : (
                                            <div className="field">
                                                <label htmlFor="business_name">
                                                    Business name
                                                </label>
                                                <Input
                                                    id="business_name"
                                                    name="business_name"
                                                    required
                                                    tabIndex={4}
                                                    autoComplete="organization"
                                                    placeholder="Zenith Solutions Group"
                                                />
                                                <InputError
                                                    message={
                                                        errors.business_name
                                                    }
                                                    className="mt-1 text-[11px]"
                                                />
                                            </div>
                                        )}

                                        {/* A student who proved their school
                                address with a code may choose a password
                                now, to log in with it as well as a code;
                                or skip it and set one later in Settings. */}
                                        {pendingIdentity?.provider ===
                                            'school-email' && (
                                            <div
                                                style={{
                                                    display: 'grid',
                                                    gap: 6,
                                                }}
                                            >
                                                <div style={TWO_UP}>
                                                    <div className="field">
                                                        <label htmlFor="password">
                                                            Password (optional)
                                                        </label>
                                                        <Input
                                                            id="password"
                                                            name="password"
                                                            type="password"
                                                            tabIndex={5}
                                                            autoComplete="new-password"
                                                            placeholder="••••••••"
                                                            {...{
                                                                passwordrules:
                                                                    passwordRules,
                                                            }}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.password
                                                            }
                                                            className="mt-1 text-[11px]"
                                                        />
                                                    </div>

                                                    <div className="field">
                                                        <label htmlFor="password_confirmation">
                                                            Confirm password
                                                        </label>
                                                        <Input
                                                            id="password_confirmation"
                                                            name="password_confirmation"
                                                            type="password"
                                                            tabIndex={6}
                                                            autoComplete="new-password"
                                                            placeholder="••••••••"
                                                            {...{
                                                                passwordrules:
                                                                    passwordRules,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                                <span
                                                    style={{
                                                        fontSize: 11.5,
                                                        color: MUTED,
                                                    }}
                                                >
                                                    Lets you log in with your
                                                    school email and password,
                                                    as well as a code. Skip it
                                                    and set one later in
                                                    Settings if you like.
                                                </span>
                                            </div>
                                        )}

                                        {/* A Google or Microsoft sign up never
                                gets a password: that is how they sign in. They
                                can still set one later through the password
                                reset flow. */}
                                        {!pendingIdentity && (
                                            <div style={TWO_UP}>
                                                <div className="field">
                                                    <label htmlFor="password">
                                                        Password
                                                    </label>
                                                    <Input
                                                        id="password"
                                                        name="password"
                                                        type="password"
                                                        required
                                                        tabIndex={5}
                                                        autoComplete="new-password"
                                                        placeholder="••••••••"
                                                        {...{
                                                            passwordrules:
                                                                passwordRules,
                                                        }}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.password
                                                        }
                                                        className="mt-1 text-[11px]"
                                                    />
                                                </div>

                                                <div className="field">
                                                    <label htmlFor="password_confirmation">
                                                        Confirm password
                                                    </label>
                                                    <Input
                                                        id="password_confirmation"
                                                        name="password_confirmation"
                                                        type="password"
                                                        required
                                                        tabIndex={6}
                                                        autoComplete="new-password"
                                                        placeholder="••••••••"
                                                        {...{
                                                            passwordrules:
                                                                passwordRules,
                                                        }}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.password_confirmation
                                                        }
                                                        className="mt-1 text-[11px]"
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        {/*
                                         * Clients may let Google prove their
                                         * address. A student reaching this form
                                         * has already proved theirs, with the
                                         * code or Microsoft, on the step before.
                                         */}
                                        {!isStudent && (
                                            <>
                                                {canLoginWithGoogle &&
                                                    !googleProfile && (
                                                        <GoogleAuthButton
                                                            href={googleRedirect.url(
                                                                {
                                                                    query: {
                                                                        intent: 'register',
                                                                    },
                                                                },
                                                            )}
                                                            tabIndex={7}
                                                        />
                                                    )}
                                                {googleSetupHint &&
                                                    !googleProfile && (
                                                        <GoogleSetupHint />
                                                    )}
                                            </>
                                        )}

                                        <label
                                            style={{
                                                display: 'flex',
                                                gap: 10,
                                                alignItems: 'flex-start',
                                                fontSize: 11.5,
                                                lineHeight: 1.5,
                                                cursor: 'pointer',
                                                color: 'color-mix(in srgb, var(--color-text) 62%, transparent)',
                                                marginTop: 2,
                                            }}
                                        >
                                            <input
                                                type="checkbox"
                                                name="terms"
                                                value="1"
                                                tabIndex={8}
                                                style={{
                                                    accentColor:
                                                        'var(--color-accent)',
                                                    width: 15,
                                                    height: 15,
                                                    flex: 'none',
                                                    marginTop: 1,
                                                }}
                                            />
                                            {/* Colour is deliberately absent from
                                            the three links below: it lives on
                                            a[data-inline-link] in nocturne.css, where
                                            :hover can reach it. */}
                                            <span>
                                                Yes, I understand and agree to
                                                the SDPC{' '}
                                                <a
                                                    href={legal.url(
                                                        'terms-of-service',
                                                    )}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    data-inline-link=""
                                                >
                                                    Terms of Service
                                                </a>
                                                , including the{' '}
                                                <a
                                                    href={legal.url(
                                                        'user-agreement',
                                                    )}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    data-inline-link=""
                                                >
                                                    User Agreement
                                                </a>{' '}
                                                and{' '}
                                                <a
                                                    href={legal.url(
                                                        'privacy-policy',
                                                    )}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    data-inline-link=""
                                                >
                                                    Privacy Policy
                                                </a>
                                                .
                                            </span>
                                        </label>
                                        <InputError
                                            message={errors.terms}
                                            className="text-[11px]"
                                        />

                                        <Btn
                                            type="submit"
                                            variant="primary"
                                            block
                                            tabIndex={9}
                                            data-test="register-user-button"
                                            style={{ paddingBlock: 9 }}
                                        >
                                            {processing && <Spinner />}
                                            Create my account
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
                            Already registered?{' '}
                            <Btn
                                asChild
                                variant="ghost"
                                style={{ fontSize: 12.5 }}
                            >
                                <Link
                                    href={
                                        teamInvitation
                                            ? login.url({
                                                  query: {
                                                      invitation:
                                                          teamInvitation.code,
                                                  },
                                              })
                                            : login.url()
                                    }
                                    data-test="team-invitation-login-link"
                                    tabIndex={10}
                                >
                                    Log in
                                </Link>
                            </Btn>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}

/**
 * The Student tab's first two steps: the school email, then its code.
 *
 * Laid out like the Client tab's "Continue with Google": the address is
 * settled first, and only then does the form ask for a name — with no
 * password, because the code has just proved the address the way Google
 * would. Once the code comes back the server holds the address and the page
 * reloads into that form with a "Continuing as …" banner.
 */
function SchoolEmailStep({
    schoolDomains,
    code,
    alternative,
}: {
    schoolDomains: string[];
    code: SchoolEmailCode | null;
    /** Another way to prove the address, e.g. the school's Microsoft account. */
    alternative?: React.ReactNode;
}) {
    const [email, setEmail] = useState('');

    if (code) {
        return (
            <EmailCodeEntry
                email={code.email}
                codeLength={code.codeLength}
                expiresAfter={code.expiresAfter}
                secondsUntilResend={code.secondsUntilResend}
                submitUrl={verifySchoolEmailCode.url()}
                resendUrl={resendSchoolEmailCode.url()}
                submitLabel="Verify my school email"
                onStartOver={() => router.delete(forgetIdentity.url())}
                startOverLabel="Use a different email"
            />
        );
    }

    return (
        <Form
            {...sendSchoolEmailCode.form()}
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
                        Start with your school email. We&apos;ll send a code to
                        it to confirm it&apos;s yours — no password needed.
                    </p>

                    <SchoolEmailField
                        value={email}
                        onChange={setEmail}
                        schoolDomains={schoolDomains}
                        error={errors.school_email}
                        tabIndex={1}
                        autoFocus
                    />

                    <Btn
                        type="submit"
                        variant="primary"
                        block
                        tabIndex={2}
                        data-test="send-school-email-code"
                        style={{ paddingBlock: 9 }}
                    >
                        {processing && <Spinner />}
                        Send code
                    </Btn>

                    {alternative}
                </>
            )}
        </Form>
    );
}
