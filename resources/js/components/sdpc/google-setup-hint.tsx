import OAuthSetupHint from '@/components/sdpc/oauth-setup-hint';

/**
 * Shown in place of the Google button while credentials are missing.
 * See OAuthSetupHint.
 */
export default function GoogleSetupHint() {
    return (
        <OAuthSetupHint
            provider="Google"
            variables={['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET']}
        />
    );
}
