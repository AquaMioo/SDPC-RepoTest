<?php

namespace App\Enums;

enum VerificationProvider: string
{
    /**
     * A third party confirming the student is enrolled.
     *
     * Optional by design. Nothing on the platform is gated on it — it adds a
     * badge and gives an administrator one more piece of evidence.
     */
    case SheerId = 'sheerid';

    /**
     * The document an administrator reviews by hand.
     *
     * This is the path that actually grants a verified account, and it existed
     * before any third party did.
     */
    case Document = 'document';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::SheerId => 'SheerID',
            self::Document => 'Uploaded document',
        };
    }
}
