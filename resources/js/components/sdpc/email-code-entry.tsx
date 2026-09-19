import { router, useForm } from '@inertiajs/react';
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

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

type Props = {
    /** The address the code went to, shown so a typo is obvious. */
    email: string;
    codeLength: number;
    expiresAfter: number;
    /** Seconds before another code may be asked for. */
    secondsUntilResend: number;
    /** Where the code is posted. */
    submitUrl: string;
    /** Where "Send another" posts. */
    resendUrl: string;
    submitLabel: string;
    /** Throws the code away and goes back to typing an address. */
    onStartOver: () => void;
    startOverPrompt?: string;
    startOverLabel?: string;
    /** Replaces the "We sent a code to…" line. */
    intro?: React.ReactNode;
    /** Posted alongside the code, e.g. "Keep me logged in". */
    extraData?: () => Record<string, unknown>;
    /** Shown between the code and the button. */
    children?: React.ReactNode;
};

/**
 * Six boxes for an emailed code, a button, and a way to ask for another.
 *
 * One component for every place SDPC mails a code — finishing a sign up, the
 * Student tab's school email, and signing a student in — so they all behave the
 * same: typing the last digit submits, a wrong code clears the boxes, and
 * "Send another" counts down instead of letting somebody hammer it.
 */
export default function EmailCodeEntry({
    email,
    codeLength,
    expiresAfter,
    secondsUntilResend,
    submitUrl,
    resendUrl,
    submitLabel,
    onStartOver,
    startOverPrompt = 'Wrong address?',
    startOverLabel = 'Start over',
    intro,
    extraData,
    children,
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
        transform(() => ({ code: value, ...(extraData?.() ?? {}) }));

        post(submitUrl, { preserveScroll: true, onError: () => setCode('') });
    };

    const askForAnother = () => {
        router.post(
            resendUrl,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setCooldown(60),
            },
        );
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <p
                style={{
                    margin: 0,
                    fontSize: 12.5,
                    lineHeight: 1.6,
                    color: MUTED,
                    textAlign: 'center',
                }}
            >
                {intro ?? (
                    <>
                        We sent a {codeLength}-digit code to{' '}
                        <b style={{ color: 'var(--color-text)' }}>{email}</b>.
                        It expires in {expiresAfter} minutes.
                    </>
                )}
            </p>

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
                    // Typing the last digit is the whole intent; making them
                    // reach for a button as well is friction for its own sake.
                    onComplete={submit}
                    disabled={processing}
                    autoFocus
                    data-test="otp-input"
                >
                    <InputOTPGroup>
                        {Array.from({ length: codeLength }).map((_, index) => (
                            <InputOTPSlot key={index} index={index} />
                        ))}
                    </InputOTPGroup>
                </InputOTP>

                <InputError message={errors.code} className="text-[11px]" />

                {children}

                <Btn
                    type="submit"
                    variant="primary"
                    block
                    disabled={processing || code.length < codeLength}
                    data-test="confirm-code-button"
                    style={{ paddingBlock: 9 }}
                >
                    {processing && <Spinner />}
                    {submitLabel}
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
                    type="button"
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

            <div style={{ textAlign: 'center', fontSize: 12, color: MUTED }}>
                {startOverPrompt}{' '}
                <Btn
                    type="button"
                    variant="ghost"
                    style={{ fontSize: 12 }}
                    onClick={onStartOver}
                    data-test="start-over-button"
                >
                    {startOverLabel}
                </Btn>
            </div>
        </div>
    );
}
