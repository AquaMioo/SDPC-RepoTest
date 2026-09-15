/**
 * Things that count as using the account even with nobody touching the page.
 *
 * The heartbeat normally only reports a tab somebody is interacting with, so a
 * tab left open on a shared machine stops holding the account. A video call is
 * the exception: people sit through one without clicking anything, and being
 * signed out of a meeting because somebody else signed in would be worse than
 * the lock itself.
 */
let holds = 0;

/**
 * Keep the account counting as in use until the returned function is called.
 *
 * Shaped to be returned straight from a useEffect.
 */
export function holdAccountInUse(): () => void {
    holds += 1;

    let released = false;

    return () => {
        if (!released) {
            released = true;
            holds -= 1;
        }
    };
}

export function isAccountHeldInUse(): boolean {
    return holds > 0;
}
