/**
 * Why this device was just signed out, or refused, on the login screen.
 *
 * Set by App\Http\Middleware\EnforceSingleSession when the account is in use on
 * another device. Styled as a warning rather than the green status line: it is
 * not a confirmation, it is somebody being told they could not get in.
 */
export default function AccountSessionWarning({
    message,
}: {
    message?: string | null;
}) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            data-test="account-session-warning"
            className="border border-amber-600/40 bg-amber-500/10 px-3 py-2 text-center text-xs leading-relaxed text-amber-700 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-300"
            style={{ borderRadius: 'var(--radius-md)' }}
        >
            {message}
        </div>
    );
}
