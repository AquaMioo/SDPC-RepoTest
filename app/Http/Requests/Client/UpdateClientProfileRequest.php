<?php

namespace App\Http\Requests\Client;

use App\Models\ClientProfile;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $profile = $this->user()?->currentTeam?->clientProfile;

        return $profile instanceof ClientProfile && Gate::allows('update', $profile);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:5000'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            /*
             * Both come from the seeded locations list, and the pair is checked
             * together in withValidator — each half is a real value on its own,
             * so column rules alone would accept a province and a city that do
             * not belong to each other.
             */
            'city' => ['nullable', 'string', 'max:255', Rule::exists(Location::class, 'city')],
            'province' => ['nullable', 'string', 'max:255', Rule::exists(Location::class, 'province')],
            // Digits only. The form strips anything else as it is typed, so a
            // rejection here means the field was posted around the form.
            'phone_number' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],

            'logo' => ['nullable', 'image', 'max:'.config('uploads.max_image_kilobytes')],
            'permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('uploads.max_document_kilobytes')],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $province = $this->input('province');
            $city = $this->input('city');

            // Nothing to cross-check until both are given, and either alone is
            // already reported by its own rule.
            if (blank($province) || blank($city)) {
                return;
            }

            if (! Location::pairExists($province, $city)) {
                $validator->errors()->add(
                    'city',
                    __(':city is not in :province.', ['city' => $city, 'province' => $province]),
                );
            }
        });
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
         * runs, so the `uploaded` rule — not `max` — is what a too-large file
         * actually trips. Its default wording ("failed to upload") reads like
         * a broken server, so both name the real ceiling, read from PHP rather
         * than written down, because the two drift apart the moment either the
         * ini or the rule is changed.
         */
        $phpLimit = ini_get('upload_max_filesize');
        $imageLimit = round(config('uploads.max_image_kilobytes') / 1024);
        $documentLimit = round(config('uploads.max_document_kilobytes') / 1024);

        return [
            'logo.max' => "The business logo may not be larger than {$imageLimit} MB.",
            'logo.uploaded' => "The business logo is too large for the server to accept. This machine allows uploads up to {$phpLimit}.",
            'phone_number.regex' => 'The phone number may contain digits only.',
            'permit.max' => "The business permit may not be larger than {$documentLimit} MB.",
            'permit.uploaded' => "The business permit is too large for the server to accept. This machine allows uploads up to {$phpLimit}.",
        ];
    }
}
