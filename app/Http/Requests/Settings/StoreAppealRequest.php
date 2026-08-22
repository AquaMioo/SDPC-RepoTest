<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only an account with a decision standing against it has anything to
     * answer. Approved and pending accounts are refused outright rather than
     * being allowed to file an appeal nobody could act on.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->mayAppeal();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:30', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.min' => 'Please explain your side in at least 30 characters, so an administrator has something to weigh.',
        ];
    }
}
