<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
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
        return [
            ...$this->profileRules($this->user()->id),
            'avatar' => $this->avatarRules(),
        ];
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
