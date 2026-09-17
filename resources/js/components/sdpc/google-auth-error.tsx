import OAuthError from '@/components/sdpc/oauth-error';

/**
 * The reason the last Google attempt was turned away. See OAuthError.
 */
export default function GoogleAuthError() {
    return <OAuthError provider="google" />;
}
