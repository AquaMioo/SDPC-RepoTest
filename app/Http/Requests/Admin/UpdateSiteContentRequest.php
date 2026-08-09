<?php

namespace App\Http\Requests\Admin;

use App\Enums\SiteContentKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteContentRequest extends FormRequest
{
    /**
     * The longest a single block of copy may be.
     */
    private const MAX_LENGTH = 5000;

    /**
     * Get the validation rules that apply to the request.
     *
     * Every block is optional: clearing one is a legitimate edit, and the
     * screen always submits all three whether or not they were touched.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SiteContentKey::values() as $key) {
            $rules[$key] = ['nullable', 'string', 'max:'.self::MAX_LENGTH];
        }

        return $rules;
    }

    /**
     * Get the submitted copy, keyed by block.
     *
     * @return array<string, string|null>
     */
    public function blocks(): array
    {
        $blocks = [];

        foreach (SiteContentKey::cases() as $key) {
            $body = $this->validated($key->value);

            // An empty textarea is stored as null rather than "", so "never
            // written" and "deliberately cleared" read the same downstream.
            $blocks[$key->value] = blank($body) ? null : $body;
        }

        return $blocks;
    }
}
