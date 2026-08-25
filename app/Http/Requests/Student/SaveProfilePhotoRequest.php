<?php

namespace App\Http\Requests\Student;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The picture a client sees beside every message and proposal.
 *
 * Shares its rules with the settings screen through ProfileValidationRules, so
 * the ceiling on an upload is one number in one place.
 */
class SaveProfilePhotoRequest extends FormRequest
{
    use ProfileValidationRules;

    /** The shortest side the dialog asks for, and therefore accepts. */
    public const MINIMUM_PIXELS = 400;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                ...$this->avatarRules(),
                /*
                 * The dialog promises "at least 400×400", so something has to
                 * hold it to that. A photo smaller than this is drawn scaled
                 * up beside every message and proposal, which is where a
                 * client forms their first impression of somebody.
                 */
                'dimensions:min_width='.self::MINIMUM_PIXELS.',min_height='.self::MINIMUM_PIXELS,
            ],
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
            'photo.required' => 'Choose a photo first.',
            'photo.image' => 'The profile photo must be an image.',
            'photo.dimensions' => 'The profile photo must be at least '.self::MINIMUM_PIXELS.'×'.self::MINIMUM_PIXELS.' pixels.',
            'photo.max' => "The profile photo may not be larger than {$imageLimit} MB.",
            'photo.uploaded' => "The profile photo is too large for the server to accept. This machine allows uploads up to {$phpLimit}.",
        ];
    }
}
