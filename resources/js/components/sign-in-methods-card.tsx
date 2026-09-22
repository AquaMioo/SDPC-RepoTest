import { Form } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Tag } from '@/components/sdpc/tag';
import { Spinner } from '@/components/ui/spinner';
import {
    code as sendPasswordCode,
    store as storePassword,
} from '@/routes/password-setup';
import {
    link as linkGoogle,
    unlink as unlinkGoogle,
} from '@/routes/student/google';

export type SignInMethods = {
    hasPassword: boolean;
    /** The account address is a school one, so a login code can go to it. */
    schoolEmailCode: boolean;
    /** False while Microsoft sign-in is not configured on the server. */
    microsoftAvailable: boolean;
    microsoftLinked: boolean;
    /** False while Google sign-in is not configured on the server. */
    googleAvailable: boolean;
    googleLinked: boolean;
    googleEmail: string | null;
    /** False when Google is the account's only way in. */
    canUnlinkGoogle: boolean;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const ROW: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 10,
};

/**
 * Settings → Sign-in methods. Students only.
 *
 * A student signs in with their school address, and a school mailbox closes
 * at graduation. The personal Google account bound here is how they still get
 * in afterwards, so the card says that plainly rather than burying it.
 *
 * Linking is a full page navigation to Google — Inertia cannot follow a
 * cross-origin redirect — so it is a plain anchor, not a <Link>.
 */
export default function SignInMethodsCard({
    methods,
}: {
    methods: SignInMethods | null;
}) {
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);

    if (methods === null) {
        return null;
    }

    return (
        <div
            className="card elev-sm"
            style={{ marginTop: 24, padding: 20, gap: 14 }}
            data-test="sign-in-methods-card"
        >
            <h6 style={{ margin: 0 }}>Sign-in methods</h6>

            {methods.schoolEmailCode && (
                <div style={ROW}>
                    <div style={{ marginRight: 'auto' }}>
                        <div style={{ fontSize: 13.5 }}>School email code</div>
                        <div style={{ fontSize: 12, color: MUTED(58) }}>
                            On the login page, choose “Log in with a code” and
                            we email one to your school address.
                        </div>
                    </div>
                    <Tag variant="accent">On</Tag>
                </div>
            )}

            {(methods.microsoftAvailable || methods.microsoftLinked) && (
                <div style={ROW}>
                    <div style={{ marginRight: 'auto' }}>
                        <div style={{ fontSize: 13.5 }}>
                            School Microsoft account
                        </div>
                        <div style={{ fontSize: 12, color: MUTED(58) }}>
                            {methods.microsoftLinked
                                ? 'Use “Continue with Microsoft” on the login page.'
                                : 'Links itself the first time you use “Continue with Microsoft” on the login page.'}
                        </div>
                    </div>
                    <Tag
                        variant={methods.microsoftLinked ? 'accent' : 'outline'}
                    >
                        {methods.microsoftLinked ? 'Linked' : 'Not linked'}
                    </Tag>
                </div>
            )}

            <div style={ROW}>
                <div style={{ marginRight: 'auto' }}>
                    <div style={{ fontSize: 13.5 }}>Password</div>
                    <div style={{ fontSize: 12, color: MUTED(58) }}>
                        {methods.hasPassword
                            ? 'Log in with your school email and this password, or keep using a code.'
                            : methods.schoolEmailCode
                              ? 'Optional. Set one to log in with your school email and password as well as a code.'
                              : 'None set. Use “Forgot password” on the login page to add one.'}
                    </div>
                </div>
                <Tag variant={methods.hasPassword ? 'accent' : 'outline'}>
                    {methods.hasPassword ? 'Set' : 'Not set'}
                </Tag>
            </div>

            {!methods.hasPassword && methods.schoolEmailCode && <SetPassword />}

            <div
                style={{ height: 1, background: 'var(--color-divider)' }}
                aria-hidden
            />

            <div style={ROW}>
                <div style={{ marginRight: 'auto', minWidth: 0 }}>
                    <div style={{ fontSize: 13.5 }}>
                        Personal Google account
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: MUTED(58),
                            overflowWrap: 'anywhere',
                        }}
                        data-test="linked-google-email"
                    >
                        {methods.googleLinked
                            ? methods.googleEmail
                            : 'Your school email closes when you graduate. Link a personal Google account now so you can still sign in afterwards.'}
                    </div>
                </div>
                <Tag variant={methods.googleLinked ? 'accent' : 'outline'}>
                    {methods.googleLinked ? 'Linked' : 'Not linked'}
                </Tag>
            </div>

            {!methods.googleAvailable ? (
                <span style={{ fontSize: 12, color: MUTED(58) }}>
                    Google sign-in is not available right now.
                </span>
            ) : confirmingRemoval ? (
                <Form
                    {...unlinkGoogle.form()}
                    options={{ preserveScroll: true }}
                    onFinish={() => setConfirmingRemoval(false)}
                    style={{ ...ROW, fontSize: 12.5 }}
                >
                    {({ processing }) => (
                        <>
                            <span style={{ marginRight: 'auto' }}>
                                Remove this Google account? You will not be able
                                to sign in with it.
                            </span>
                            <Btn
                                type="button"
                                variant="ghost"
                                onClick={() => setConfirmingRemoval(false)}
                            >
                                Keep it
                            </Btn>
                            <Btn
                                type="submit"
                                variant="primary"
                                data-test="confirm-unlink-google"
                            >
                                {processing && <Spinner />}
                                Remove
                            </Btn>
                        </>
                    )}
                </Form>
            ) : (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                    <Btn
                        asChild
                        variant={methods.googleLinked ? 'secondary' : 'primary'}
                    >
                        <a href={linkGoogle.url()} data-test="link-google">
                            {methods.googleLinked
                                ? 'Use a different Google account'
                                : 'Link a Google account'}
                        </a>
                    </Btn>

                    {methods.googleLinked && (
                        <Btn
                            type="button"
                            variant="ghost"
                            disabled={!methods.canUnlinkGoogle}
                            title={
                                methods.canUnlinkGoogle
                                    ? undefined
                                    : 'Google is the only way into this account.'
                            }
                            onClick={() => setConfirmingRemoval(true)}
                            data-test="unlink-google"
                        >
                            Remove
                        </Btn>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * A first password, for a student who signed up with a code and has none.
 *
 * There is no current password to confirm, so a code to the school address
 * stands in for it: send the code, then type it with the new password. After
 * that the ordinary login form takes the school email and this password, and
 * the code keeps working too.
 */
function SetPassword() {
    const [open, setOpen] = useState(false);
    const [codeSent, setCodeSent] = useState(false);

    if (!open) {
        return (
            <div>
                <Btn
                    type="button"
                    variant="secondary"
                    onClick={() => setOpen(true)}
                    data-test="set-password"
                >
                    Set a password
                </Btn>
            </div>
        );
    }

    return (
        <div
            className="profile-inline-form"
            style={{
                display: 'grid',
                gap: 12,
                padding: 14,
                borderRadius: 'var(--radius-md)',
                background: MUTED(4),
            }}
        >
            <Form
                {...sendPasswordCode.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => setCodeSent(true)}
                style={{ ...ROW, fontSize: 12.5 }}
            >
                {({ processing, errors }) => (
                    <>
                        <span style={{ marginRight: 'auto', color: MUTED(70) }}>
                            {codeSent
                                ? 'We emailed a code to your school address. It expires soon.'
                                : 'First we email a code to your school address, to check it is you.'}
                        </span>
                        <Btn
                            type="submit"
                            variant={codeSent ? 'ghost' : 'primary'}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {codeSent ? 'Send another code' : 'Email me a code'}
                        </Btn>
                        <InputError
                            message={errors.password}
                            className="w-full text-[11px]"
                        />
                    </>
                )}
            </Form>

            {codeSent && (
                <Form
                    {...storePassword.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    style={{ display: 'grid', gap: 10 }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="field">
                                <label htmlFor="password-code">Code</label>
                                <Input
                                    id="password-code"
                                    name="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={12}
                                    autoFocus
                                />
                                <InputError
                                    message={errors.code}
                                    className="mt-1 text-[11px]"
                                />
                            </div>
                            <div className="field">
                                <label htmlFor="new-password">
                                    New password
                                </label>
                                <PasswordInput
                                    id="new-password"
                                    name="password"
                                    autoComplete="new-password"
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-1 text-[11px]"
                                />
                            </div>
                            <div className="field">
                                <label htmlFor="new-password-confirmation">
                                    Confirm password
                                </label>
                                <PasswordInput
                                    id="new-password-confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                />
                            </div>
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    justifyContent: 'flex-end',
                                }}
                            >
                                <Btn
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Btn>
                                <Btn
                                    type="submit"
                                    variant="primary"
                                    disabled={processing}
                                    data-test="save-password"
                                >
                                    {processing && <Spinner />}
                                    Set password
                                </Btn>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
