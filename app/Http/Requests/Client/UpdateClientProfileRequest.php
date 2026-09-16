<?php

namespace App\Http\Requests\Client;

use App\Enums\Industry;
use App\Enums\OrganizationSize;
use App\Models\Barangay;
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
     * +63, then a mobile (9 and nine more digits) or a landline (area code
     * and number, nine digits, never starting with 0 or 1).
     */
    public const PHILIPPINE_NUMBER = '/^\+63(9\d{9}|[2-8]\d{8})$/';

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
            'industry' => ['nullable', Rule::enum(Industry::class)],
            'organization_size' => ['nullable', Rule::enum(OrganizationSize::class)],
            /* One line, so it stays a line. */
            'tagline' => ['nullable', 'string', 'max:160'],
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
            /* Checked against the chosen city in withValidator. */
            'barangay' => ['nullable', 'string', 'max:255'],
            /*
             * A Philippine number, stored as +63 and the national number:
             * ten digits for a mobile (9XX XXX XXXX), nine for a landline
             * with its area code. prepareForValidation() has already turned
             * 0917…, 63917… and "+63 917 …" into that shape.
             */
            'phone_number' => ['nullable', 'string', 'regex:'.self::PHILIPPINE_NUMBER],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],

            /*
             * There is no `permit` rule any more. Nobody reviews permits, and
             * accepting one used to reset the profile to Pending with no way
             * back - see App\Actions\Client\UpdateClientProfile.
             */
            'logo' => ['nullable', 'image', 'max:'.config('uploads.max_image_kilobytes')],
        ];
    }

    /**
     * Put the contact fields into the shape the rules and the database expect.
     *
     * Only fields that were sent are touched: the company details dialog
     * posts to the same route without them, and merging a null in would wipe
     * whatever the contacts dialog saved.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];

        if ($this->has('phone_number')) {
            $normalised['phone_number'] = $this->philippineNumber($this->input('phone_number'));
        }

        foreach (['website_url', 'facebook_url'] as $field) {
            if ($this->has($field)) {
                $normalised[$field] = $this->withScheme($this->input($field));
            }
        }

        $this->merge($normalised);
    }

    /**
     * "0917 123 4567", "639171234567" and "+63 917 123 4567" all become
     * "+639171234567".
     *
     * Anything carrying more than digits and the usual separators is passed
     * through untouched, so the rule rejects it rather than it being quietly
     * turned into a number nobody typed.
     */
    protected function philippineNumber(mixed $raw): mixed
    {
        if (! is_string($raw) || trim($raw) === '' || preg_match('/^[\d\s().+-]+$/', $raw) !== 1) {
            return $raw;
        }

        $digits = preg_replace('/\D/', '', $raw);

        /* A national number is at most ten digits, so anything longer carries the country code. */
        if (str_starts_with($digits, '63') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        /* The trunk 0 a local number is dialled with has no place after +63. */
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+63'.$digits;
    }

    /**
     * Let somebody type "yourbusiness.com" and have it saved as a link.
     */
    protected function withScheme(mixed $raw): mixed
    {
        if (! is_string($raw) || trim($raw) === '') {
            return $raw;
        }

        $url = trim($raw);

        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1 ? $url : 'https://'.$url;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $province = $this->input('province');
            $city = $this->input('city');
            $barangay = $this->input('barangay');

            /*
             * A barangay only means something inside a city, so it needs the
             * city and province beside it, and has to be one of that city's.
             */
            if (filled($barangay)) {
                if (blank($province) || blank($city)) {
                    $validator->errors()->add('barangay', __('Choose the province and city before the barangay.'));
                } elseif (! Barangay::existsIn($province, $city, $barangay)) {
                    $validator->errors()->add(
                        'barangay',
                        __(':barangay is not a barangay of :city.', ['barangay' => $barangay, 'city' => $city]),
                    );
                }
            }

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

        return [
            'logo.max' => "The business logo may not be larger than {$imageLimit} MB.",
            'logo.uploaded' => "The business logo is too large for the server to accept. This machine allows uploads up to {$phpLimit}.",
            'phone_number.regex' => 'Enter a Philippine number after +63: a mobile such as 917 123 4567, or a landline with its area code.',
            'website_url.url' => 'Enter a web address, such as yourbusiness.com.',
            'facebook_url.url' => 'Enter the link to your Facebook page, such as facebook.com/yourbusiness.',
        ];
    }
}
