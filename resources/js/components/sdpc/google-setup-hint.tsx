/**
 * Shown in place of the Google button while credentials are missing.
 *
 * The button hides itself when Google is not configured, which is right for
 * visitors but leaves a developer staring at a gap with no explanation. The
 * server only sends this flag outside production, so it never reaches users.
 */
export default function GoogleSetupHint() {
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
            <b style={{ color: 'var(--color-text)' }}>Google sign-in is off.</b>{' '}
            Add <code style={code}>GOOGLE_CLIENT_ID</code> and{' '}
            <code style={code}>GOOGLE_CLIENT_SECRET</code> to your{' '}
            <code style={code}>.env</code> and reload. Local development only —
            this notice never appears in production.
        </div>
    );
}
