/**
 * Shown in place of a sign-in button while its credentials are missing.
 *
 * The buttons hide themselves when their provider is not configured, which is
 * right for visitors but leaves a developer staring at a gap with no
 * explanation. The server only sends the flag outside production, so this
 * never reaches users.
 */
export default function OAuthSetupHint({
    provider,
    variables,
}: {
    /** The provider's name as the notice says it, e.g. "Google". */
    provider: string;
    /** The .env keys that switch the provider on. */
    variables: [string, string];
}) {
    const code: React.CSSProperties = {
        borderRadius: 3,
        padding: '0 4px',
        background: 'color-mix(in srgb, var(--color-accent) 18%, transparent)',
        color: 'var(--color-accent-200)',
    };

    return (
        <div
            style={{
                border: '1px dashed var(--color-divider)',
                borderRadius: 'var(--radius-md)',
                padding: '8px 10px',
                textAlign: 'center',
                fontSize: 11,
                lineHeight: 1.6,
                color: 'color-mix(in srgb, var(--color-text) 62%, transparent)',
            }}
        >
            <b style={{ color: 'var(--color-text)' }}>
                {provider} sign-in is off.
            </b>{' '}
            Add <code style={code}>{variables[0]}</code> and{' '}
            <code style={code}>{variables[1]}</code> to your{' '}
            <code style={code}>.env</code> and reload. Local development only —
            this notice never appears in production.
        </div>
    );
}
