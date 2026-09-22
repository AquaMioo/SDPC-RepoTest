<?php

namespace App\Rules;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The address must belong to a student who already has an SDPC account.
 *
 * A team invitation used to go out to any address, mailing whoever owned it an
 * invitation they could only act on by registering first. Nothing on the team
 * screen said so, so a lead who mistyped an address — or invited a classmate
 * who had never signed up — saw "Invitation sent" and then waited on somebody
 * who was never coming. Addresses are matched case-insensitively, the way
 * TeamInvitation::pendingFor does.
 */
class RegisteredStudent implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $account = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $value)])
            ->first();

        if ($account === null) {
            $fail(__('No SDPC account uses this email address. Ask them to sign up first, then invite them.'));

            return;
        }

        if (! $account->hasRole(UserRole::Student)) {
            $fail(__('Only students can be invited to a team.'));
        }
    }
}
