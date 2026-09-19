import { Form, Head, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon } from '@phosphor-icons/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import type { Auth } from '@/types';

export default function ConfirmPassword() {
    const home = usePage<{ auth?: Auth }>().props.auth?.home ?? '/';

    /*
     * This screen interrupts whatever the person was doing in Settings, so
     * Back returns them there. Opened on its own, with no page behind it, it
     * leads to their dashboard instead.
     */
    const goBack = (): void => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(home);
        }
    };

    return (
        <>
            <Head title="Confirm password" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                Confirm password
                            </Button>
                        </div>
                    </div>
                )}
            </Form>

            <button
                type="button"
                onClick={goBack}
                data-inline-link=""
                data-test="confirm-password-back"
                style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 6,
                    border: 0,
                    fontSize: 13,
                }}
            >
                <ArrowLeftIcon />
                Back
            </button>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirm password',
    description:
        'This is a secure area of the application. Please confirm your password before continuing.',
};
