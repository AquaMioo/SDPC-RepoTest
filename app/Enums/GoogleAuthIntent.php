<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * Which button started the Google flow.
 *
 * Google never creates an account — everyone registers through the form so the
 * role, school or business name and the terms are actually collected. The two
 * buttons therefore fail for opposite reasons, and this is what tells them
 * apart on the way back.
 */
enum GoogleAuthIntent: string
{
    case Login = 'login';
    case Register = 'register';

    /**
     * Read the intent the redirect was started with, defaulting to signing in.
     */
    public static function fromRequest(Request $request): self
    {
        return self::tryFrom((string) $request->query('intent')) ?? self::Login;
    }

    /**
     * Get the message shown when no account matches the Google identity.
     */
    public function noAccountMessage(): string
    {
        return match ($this) {
            self::Register => __('Signing up with Google is not available. Please fill in the form to create your account first.'),
            self::Login => __('No account is registered for this Google account. Please create an account first.'),
        };
    }
}
