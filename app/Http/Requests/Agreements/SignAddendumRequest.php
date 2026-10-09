<?php

namespace App\Http\Requests\Agreements;

use App\Models\Addendum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * One party's electronic signature on the addendum (Section IX): the name
 * typed over, and the statement that they read and agree to it.
 */
class SignAddendumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $addendum = $this->route('addendum');

        return $addendum instanceof Addendum && Gate::allows('sign', $addendum);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signed_name' => ['required', 'string', 'max:120'],
            'agreed' => ['accepted'],
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
            'signed_name.required' => __('Type your full name to sign.'),
            'agreed.accepted' => __('Confirm that you have read the addendum and agree to it.'),
        ];
    }
}
