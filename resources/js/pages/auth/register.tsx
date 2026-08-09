import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import GoogleAuthButton from '@/components/sdpc/google-auth-button';
import GoogleAuthError from '@/components/sdpc/google-auth-error';
import GoogleSetupHint from '@/components/sdpc/google-setup-hint';
import { Input } from '@/components/sdpc/input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

type Role = { value: string; label: string };

/** Set once someone has come back from Google without an account yet. */
type GoogleProfile = {
    email: string;
    first_name: string;
    last_name: string;
    avatar: string | null;
};

type Props = {
    passwordRules: string;
    roles?: Role[];
    canLoginWithGoogle?: boolean;
    googleSetupHint?: boolean;
    teamInvitation?: TeamInvitationContext | null;
    googleProfile?: GoogleProfile | null;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';
const TWO_UP: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
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
    teamInvitation,
    googleProfile,
}: Props) {
    const [role, setRole] = useState<string>(roles[0]?.value ?? 'client');
    const isStudent = role === 'student';

    return (
        <>
            <Head title="Register" />

            <div
                className="card elev-md"
                style={{
                    width: 420,
                    padding: 28,
                    gap: 13,
                    position: 'relative',
                }}
            >
                <h4 style={{ margin: 0, textAlign: 'center' }}>Register</h4>

                <GoogleAuthError />

                {googleProfile && (
                    <div
                        data-test="google-continuing-as"
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
                            {googleProfile.email}
                        </b>
                        . Pick your role and finish the details below — no
                        password needed.
                    </div>
                )}

                {teamInvitation && (
                    <TeamInvitationAlert
                        invitation={teamInvitation}
                        action="Register"
                    />
                )}

                <div className="seg" style={{ alignSelf: 'center' }}>
                    {roles.map((option) => (
                        <label className="seg-opt" key={option.value}>
                            <input
                                type="radio"
                                name="role-picker"
                                value={option.value}
                                checked={role === option.value}
                                onChange={() => setRole(option.value)}
                            />
                            {option.label}
                        </label>
                    ))}
                </div>

                <Form
                    {...store.form()}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    style={{ display: 'contents' }}
                >
                    {({ processing, errors }) => (
                        <>
                            {/* The segmented control lives outside the form so it
                                can drive conditional fields; its value rides
                                along here. */}
                            <input type="hidden" name="role" value={role} />

                            <div style={TWO_UP}>
                                <div className="field">
                                    <label htmlFor="last_name">Last name</label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        required
                                        tabIndex={1}
                                        autoComplete="family-name"
                                        placeholder="Clemens"
                                        defaultValue={googleProfile?.last_name}
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
                                        defaultValue={googleProfile?.first_name}
                                    />
                                    <InputError
                                        message={errors.first_name}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>
                            </div>

                            <div className="field">
                                <label htmlFor="email">Email</label>
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
                                    defaultValue={googleProfile?.email}
                                    readOnly={Boolean(googleProfile)}
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

                            {isStudent ? (
                                <div className="field">
                                    <label htmlFor="school_email">
                                        School email or student number
                                    </label>
                                    <Input
                                        id="school_email"
                                        name="school_email"
                                        required
                                        tabIndex={4}
                                        placeholder="02000xxxxxx@sti.edu.ph"
                                    />
                                    <InputError
                                        message={errors.school_email}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>
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
                                        message={errors.business_name}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>
                            )}

                            {/* A Google account never gets a password: Google
                                is how they sign in. They can still set one
                                later through the password reset flow. */}
                            {!googleProfile && (
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
                                                passwordrules: passwordRules,
                                            }}
                                        />
                                        <InputError
                                            message={errors.password}
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
                                                passwordrules: passwordRules,
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

                            {canLoginWithGoogle && !googleProfile && (
                                <GoogleAuthButton
                                    href={googleRedirect.url({
                                        query: { intent: 'register' },
                                    })}
                                    tabIndex={7}
                                />
                            )}
                            {googleSetupHint && !googleProfile && (
                                <GoogleSetupHint />
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
                                        accentColor: 'var(--color-accent)',
                                        width: 15,
                                        height: 15,
                                        flex: 'none',
                                        marginTop: 1,
                                    }}
                                />
                                Yes, I understand and agree to the SDPCC Terms
                                of Service, including the User Agreement and
                                Privacy Policy.
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

                <div
                    style={{
                        textAlign: 'center',
                        fontSize: 12.5,
                        color: MUTED,
                    }}
                >
                    Already registered?{' '}
                    <Btn asChild variant="ghost" style={{ fontSize: 12.5 }}>
                        <Link
                            href={
                                teamInvitation
                                    ? login.url({
                                          query: {
                                              invitation: teamInvitation.code,
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
        </>
    );
}
