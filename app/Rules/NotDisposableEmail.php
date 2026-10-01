<?php

namespace App\Rules;

use App\Support\DisposableEmailDomains;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Refuse temporary / disposable email addresses.
 *
 * The server-side check behind sign up and email changes. It only refuses
 * domains on the maintained blocklist (App\Support\DisposableEmailDomains);
 * any other address — a permanent provider or a company's own domain — passes,
 * so nobody is turned away for using a provider the list has never heard of.
 */
class NotDisposableEmail implements ValidationRule
{
    /**
     * @param  string|null  $currentEmail  The address the account already has. It is not
     *                                     re-checked, so an existing account can still save
     *                                     its profile; only a change to a new address is.
     */
    public function __construct(protected ?string $currentEmail = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if ($this->currentEmail !== null && mb_strtolower(trim($value)) === mb_strtolower(trim($this->currentEmail))) {
            return;
        }

        if (app(DisposableEmailDomains::class)->isDisposable($value)) {
            $fail(__('Temporary or disposable email addresses are not allowed. Please use a permanent email address.'));
        }
    }
}
