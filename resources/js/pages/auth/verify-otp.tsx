import { Head, router, useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { cancel, resend, store as confirmCode } from '@/routes/register/verify';

type Props = {
    /** The address the code went to, shown so a typo is obvious. */
    email: string;
    codeLength: number;
    expiresAfter: number;
    /** Seconds before another code may be asked for. */
    secondsUntilResend: number;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

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
    const [code, setCode] = useState('');
    const [cooldown, setCooldown] = useState(secondsUntilResend);

    const { post, processing, errors, setData, transform } = useForm({
        code: '',
    });

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }

        const timer = setInterval(
            () => setCooldown((seconds) => Math.max(0, seconds - 1)),
            1000,
        );

        return () => clearInterval(timer);
    }, [cooldown]);

    const submit = (value: string) => {
        setData('code', value);

        /*
         * onComplete fires with the value in the same tick setData is called,
         * and React state has not caught up by the time post() reads it — so
         * the code being submitted is stated outright rather than read back.
         */
        transform(() => ({ code: value }));

        post(confirmCode.url(), { onError: () => setCode('') });
    };

    const askForAnother = () => {
        router.post(
            resend.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setCooldown(60),
            },
        );
    };

    return (
        <>
            <Head title="Confirm your email" />

            <div
                className="card elev-md"
                style={{ width: '100%', maxWidth: 420, padding: 28, gap: 16 }}
            >
                <div style={{ textAlign: 'center' }}>
                    <h4 style={{ margin: 0 }}>Check your inbox</h4>
                    <p
                        style={{
                            margin: '6px 0 0',
                            fontSize: 12.5,
                            lineHeight: 1.6,
                            color: MUTED,
                        }}
                    >
                        We sent a {codeLength}-digit code to{' '}
                        <b style={{ color: 'var(--color-text)' }}>{email}</b>.
                        It expires in {expiresAfter} minutes.
                    </p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(code);
                    }}
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                        alignItems: 'center',
                    }}
                >
                    <InputOTP
                        maxLength={codeLength}
                        pattern={REGEXP_ONLY_DIGITS}
                        value={code}
                        onChange={setCode}
                        // Typing the last digit is the whole intent; making
                        // them reach for a button as well is friction for its
                        // own sake.
                        onComplete={submit}
                        disabled={processing}
                        autoFocus
                        data-test="otp-input"
                    >
                        <InputOTPGroup>
                            {Array.from({ length: codeLength }).map(
                                (_, index) => (
                                    <InputOTPSlot key={index} index={index} />
                                ),
                            )}
                        </InputOTPGroup>
                    </InputOTP>

                    <InputError message={errors.code} className="text-[11px]" />

                    <Btn
                        type="submit"
                        variant="primary"
                        block
                        disabled={processing || code.length < codeLength}
                        data-test="confirm-code-button"
                        style={{ paddingBlock: 9 }}
                    >
                        {processing && <Spinner />}
                        Confirm and create my account
                    </Btn>
                </form>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 6,
                        fontSize: 12.5,
                        color: MUTED,
                        flexWrap: 'wrap',
                    }}
                >
                    <span>Nothing arrived?</span>
                    <Btn
                        variant="ghost"
                        style={{ fontSize: 12.5 }}
                        disabled={cooldown > 0}
                        onClick={askForAnother}
                        data-test="resend-code-button"
                    >
                        {cooldown > 0
                            ? `Send another in ${cooldown}s`
                            : 'Send another'}
                    </Btn>
                </div>

                <div
                    style={{
                        textAlign: 'center',
                        fontSize: 12,
                        color: MUTED,
                    }}
                >
                    Wrong address?{' '}
                    <Btn
                        variant="ghost"
                        style={{ fontSize: 12 }}
                        onClick={() => router.delete(cancel.url())}
                        data-test="start-over-button"
                    >
                        Start over
                    </Btn>
                </div>
            </div>
        </>
    );
}
