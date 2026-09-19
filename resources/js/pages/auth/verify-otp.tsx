import { Head, router } from '@inertiajs/react';

import EmailCodeEntry from '@/components/sdpc/email-code-entry';
import { cancel, resend, store as confirmCode } from '@/routes/register/verify';

type Props = {
    /** The address the code went to, shown so a typo is obvious. */
    email: string;
    codeLength: number;
    expiresAfter: number;
    /** Seconds before another code may be asked for. */
    secondsUntilResend: number;
};

/**
 * The step between filling in the sign up form and having an account.
 *
 * Nothing has been created at this point — the form is held server side and
 * the account is written only once this code comes back. That is why "wrong
 * address?" is a link rather than a warning: starting over costs nothing,
 * because there is nothing yet to undo.
 */
export default function VerifyOtp({
    email,
    codeLength,
    expiresAfter,
    secondsUntilResend,
}: Props) {
    return (
        <>
            <Head title="Confirm your email" />

            <div
                className="card elev-md"
                style={{ width: '100%', maxWidth: 420, padding: 28, gap: 16 }}
            >
                <h4 style={{ margin: 0, textAlign: 'center' }}>
                    Check your inbox
                </h4>

                <EmailCodeEntry
                    email={email}
                    codeLength={codeLength}
                    expiresAfter={expiresAfter}
                    secondsUntilResend={secondsUntilResend}
                    submitUrl={confirmCode.url()}
                    resendUrl={resend.url()}
                    submitLabel="Confirm and create my account"
                    onStartOver={() => router.delete(cancel.url())}
                />
            </div>
        </>
    );
}
