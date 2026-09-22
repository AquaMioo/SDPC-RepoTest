import { Form, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AccountDeletionCodeController from '@/actions/App/Http/Controllers/Settings/AccountDeletionCodeController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Auth } from '@/types';

/**
 * Delete the signed-in account.
 *
 * An account with a password confirms with it. One made by a school-email code
 * or through Google has none, so it asks for a code mailed to its own address
 * first; without that it had no way to leave at all.
 */
export default function DeleteUser({
    hasPassword = true,
}: {
    hasPassword?: boolean;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const codeInput = useRef<HTMLInputElement>(null);
    const [codeSent, setCodeSent] = useState(false);
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Delete account"
                description="Delete your account and all of its resources"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>

                <Dialog onOpenChange={(open) => !open && setCodeSent(false)}>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted.{' '}
                            {hasPassword
                                ? 'Please enter your password to confirm you would like to permanently delete your account.'
                                : `Your account has no password, so we email a code to ${auth.user.email} to confirm it is you.`}
                        </DialogDescription>

                        {!hasPassword && (
                            <Form
                                {...AccountDeletionCodeController.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => {
                                    setCodeSent(true);
                                    codeInput.current?.focus();
                                }}
                                className="flex flex-wrap items-center gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <span className="mr-auto text-sm text-muted-foreground">
                                            {codeSent
                                                ? 'We emailed you a code. It expires soon.'
                                                : 'First, get a code by email.'}
                                        </span>
                                        <Button
                                            type="submit"
                                            variant={
                                                codeSent ? 'ghost' : 'secondary'
                                            }
                                            disabled={processing}
                                            data-test="send-delete-code-button"
                                        >
                                            {processing && <Spinner />}
                                            {codeSent
                                                ? 'Send another code'
                                                : 'Email me a code'}
                                        </Button>
                                        <InputError
                                            message={errors.code}
                                            className="w-full"
                                        />
                                    </>
                                )}
                            </Form>
                        )}

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() =>
                                (hasPassword
                                    ? passwordInput
                                    : codeInput
                                ).current?.focus()
                            }
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    {hasPassword ? (
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="password"
                                                className="sr-only"
                                            >
                                                Password
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Password"
                                                autoComplete="current-password"
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="delete-code"
                                                className="sr-only"
                                            >
                                                Code
                                            </Label>

                                            <Input
                                                id="delete-code"
                                                name="code"
                                                ref={codeInput}
                                                placeholder="Code from the email"
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                maxLength={12}
                                                disabled={!codeSent}
                                            />

                                            <InputError message={errors.code} />
                                        </div>
                                    )}

                                    <DialogFooter className="mt-4 gap-3">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={
                                                processing ||
                                                (!hasPassword && !codeSent)
                                            }
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
