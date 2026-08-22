import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { code as sendCode, submit as submitAppeal } from '@/routes/appeal';
import { resend } from '@/routes/appeal/code';

type Props = {
    /** Set once a code has been asked for; null on the first step. */
    email: string | null;
    codeLength: number;
    expiresAfter: number;
    secondsUntilResend: number;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/**
 * The appeal page for an account that can no longer sign in.
 *
 * Two steps: prove the address is yours with an emailed code, then write the
 * appeal. A code rather than a password because an account created through
 * Google never had one.
 *
 * Nothing on this page reveals whether an address has an account — the second
 * step appears either way.
 */
export default function Appeal({
    email,
    codeLength,
    expiresAfter,
    secondsUntilResend,
}: Props) {
    const identify = useForm({ email: '' });
    const appeal = useForm({ code: '', body: '' });

    const [cooldown, setCooldown] = useState(secondsUntilResend);

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

    return (
        <>
            <Head title="Appeal a decision" />

            <div
                className="card elev-md"
                style={{ width: 460, padding: 28, gap: 14 }}
            >
                <div style={{ textAlign: 'center' }}>
                    <h4 style={{ margin: 0 }}>Appeal a decision</h4>
                    <p
                        style={{
                            margin: '6px 0 0',
                            fontSize: 12.5,
                            lineHeight: 1.6,
                            color: MUTED,
                        }}
                    >
                        {email
                            ? `If ${email} has an account under review, we sent it a ${codeLength}-digit code. It expires in ${expiresAfter} minutes.`
                            : 'If an administrator has restricted or deactivated your account, you can ask for it to be looked at again.'}
                    </p>
                </div>

                {email === null ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            identify.post(sendCode.url());
                        }}
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <div className="field">
                            <label htmlFor="appeal-email">
                                Your account email
                            </label>
                            <Input
                                id="appeal-email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="you@email.com"
                                value={identify.data.email}
                                onChange={(event) =>
                                    identify.setData('email', event.target.value)
                                }
                            />
                            <InputError
                                message={identify.errors.email}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <Btn
                            type="submit"
                            variant="primary"
                            block
                            disabled={identify.processing}
                            data-test="send-appeal-code-button"
                            style={{ paddingBlock: 9 }}
                        >
                            {identify.processing && <Spinner />}
                            Send me a code
                        </Btn>
                    </form>
                ) : (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            appeal.post(submitAppeal.url());
                        }}
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <div className="field">
                            <label htmlFor="appeal-code">
                                Code from your email
                            </label>
                            <Input
                                id="appeal-code"
                                name="code"
                                required
                                autoFocus
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={codeLength}
                                placeholder="000000"
                                value={appeal.data.code}
                                onChange={(event) =>
                                    appeal.setData('code', event.target.value)
                                }
                            />
                            <InputError
                                message={appeal.errors.code}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <div className="field">
                            <label htmlFor="appeal-body">Your appeal</label>
                            <textarea
                                id="appeal-body"
                                name="body"
                                rows={5}
                                maxLength={2000}
                                placeholder="Explain your side. An administrator reads this alongside the reports on your account."
                                value={appeal.data.body}
                                onChange={(event) =>
                                    appeal.setData('body', event.target.value)
                                }
                                aria-invalid={Boolean(appeal.errors.body)}
                            />
                            <InputError
                                message={appeal.errors.body}
                                className="mt-1 text-[11px]"
                            />
                        </div>

                        <Btn
                            type="submit"
                            variant="primary"
                            block
                            disabled={appeal.processing}
                            data-test="submit-appeal-button"
                            style={{ paddingBlock: 9 }}
                        >
                            {appeal.processing && <Spinner />}
                            Submit appeal
                        </Btn>

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
                                onClick={() =>
                                    router.post(
                                        resend.url(),
                                        {},
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => setCooldown(60),
                                        },
                                    )
                                }
                                data-test="resend-appeal-code-button"
                            >
                                {cooldown > 0
                                    ? `Send another in ${cooldown}s`
                                    : 'Send another'}
                            </Btn>
                        </div>
                    </form>
                )}

                <div
                    style={{ textAlign: 'center', fontSize: 12.5, color: MUTED }}
                >
                    <Link href={login.url()}>Back to sign in</Link>
                </div>
            </div>
        </>
    );
}
