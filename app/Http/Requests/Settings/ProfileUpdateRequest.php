<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Rules\SchoolEmailAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->profileRules($this->user()->id),
            'avatar' => $this->avatarRules(),
        ];

        /*
         * A student signs in with their school address, so a new address has
         * to be one too. Only a CHANGE is checked: students who registered
         * before sign up asked for a school address keep saving their profile
         * with the personal one they already have.
         */
        if ($this->user()->hasRole(UserRole::Student) && $this->changesEmail()) {
            $rules['email'][] = new SchoolEmailAddress;
        }

        return $rules;
    }

    /**
     * Determine if the form is replacing the account's address.
     */
    private function changesEmail(): bool
    {
        return mb_strtolower(trim((string) $this->input('email')))
            !== mb_strtolower((string) $this->user()->email);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        /*
         * PHP discards anything over upload_max_filesize before validation
         * runs, so a too-large picture trips `uploaded`, not `max`. Its default
         * wording reads like a broken server; this names the real ceiling, read
         * from PHP so the two cannot drift apart.
         */
        $phpLimit = ini_get('upload_max_filesize');
        $imageLimit = round(config('uploads.max_image_kilobytes') / 1024);

        return [
            'avatar.max' => "The profile picture may not be larger than {$imageLimit} MB.",
            'avatar.image' => 'The profile picture must be an image.',
            'avatar.uploaded' => "The profile picture is too large for the server to accept. This machine allows uploads up to {$phpLimit}.",
        ];
    }
}
