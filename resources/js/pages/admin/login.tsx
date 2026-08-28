import { Form, Head, Link } from '@inertiajs/react';
import {
    EyeIcon,
    EyeSlashIcon,
    LockSimpleIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Spinner } from '@/components/ui/spinner';
import { useMod } from '@/hooks/use-mod';
import { store as adminLoginStore } from '@/routes/admin/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
};

const ADORN: React.CSSProperties = {
    position: 'absolute',
    left: 10,
    top: 10,
    fontSize: 15,
    opacity: 0.5,
};

/**
 * The admin portal sign in screen.
 *
 * Brings its own shell (it is excluded from the layout resolver) and stays on
 * the base palette — the deeper #0c1614 green the design gives admin screens,
 * not the #e3e3e3 the public app wears.
 */
export default function AdminLogin({ status }: Props) {
    const [revealed, setRevealed] = useState(false);

    useMod('admin');

    return (
        <div
            data-mod="admin"
            style={{
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 24,
                padding: '48px 24px',
                background: 'var(--color-bg)',
                color: 'var(--color-text)',
                fontFamily: 'var(--font-body)',
            }}
        >
            <Head title="Admin log in" />

            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                <Link
                    href="/"
                    style={{
                        fontFamily: 'var(--font-heading)',
                        fontWeight: 600,
                        fontSize: 22,
                        letterSpacing: '-0.02em',
                        color: 'var(--color-accent)',
                        textDecoration: 'none',
                    }}
                >
                    SDPC
                </Link>
                <span
                    style={{
                        fontSize: 11,
                        letterSpacing: '.14em',
                        textTransform: 'uppercase',
                        color: 'color-mix(in srgb, var(--color-text) 45%, transparent)',
                    }}
                >
                    Admin
                </span>
            </div>

            <div
                className="card elev-md"
                style={{ width: '100%', maxWidth: 370, padding: 28, gap: 14 }}
            >
                <h4 style={{ margin: '0 0 4px', textAlign: 'center' }}>
                    Log in
                </h4>

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

                <Form
                    {...adminLoginStore.form()}
                    resetOnSuccess={['password']}
                    style={{ display: 'contents' }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="field">
                                <label htmlFor="email">Username or email</label>
                                <div style={{ position: 'relative' }}>
                                    <UserIcon style={ADORN} />
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="admin"
                                        style={{ paddingLeft: 31 }}
                                    />
                                </div>
                                <InputError
                                    message={errors.email}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="password">Password</label>
                                <div style={{ position: 'relative' }}>
                                    <LockSimpleIcon style={ADORN} />
                                    <Input
                                        id="password"
                                        name="password"
                                        type={revealed ? 'text' : 'password'}
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        style={{
                                            paddingLeft: 31,
                                            paddingRight: 34,
                                        }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setRevealed((v) => !v)}
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

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    fontSize: 12,
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
                                            accentColor: 'var(--color-accent)',
                                            width: 15,
                                            height: 15,
                                        }}
                                    />
                                    Keep me logged in
                                </label>
                                <Link
                                    href={request.url()}
                                    style={{ fontSize: 12 }}
                                >
                                    Forgot password?
                                </Link>
                            </div>

                            <Btn
                                type="submit"
                                variant="primary"
                                block
                                tabIndex={4}
                                disabled={processing}
                                data-test="admin-login-button"
                                style={{ paddingBlock: 9 }}
                            >
                                {processing && <Spinner />}
                                Log in
                            </Btn>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
