<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate uploaded profile pictures.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function avatarRules(): array
    {
        return ['nullable', 'image', 'max:'.config('uploads.max_image_kilobytes')];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            /*
             * Changing to a disposable address is refused the same way signing
             * up with one is; the address the account already has is left
             * alone, so an existing account can still save its profile.
             */
            new NotDisposableEmail($userId === null ? null : User::query()->whereKey($userId)->value('email')),
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
