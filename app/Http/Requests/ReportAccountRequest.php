<?php

namespace App\Http\Requests;

use App\Enums\IssueCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reported_user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'category' => ['required', 'string', Rule::enum(IssueCategory::class)],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((int) $this->input('reported_user_id') === $this->user()->id) {
                $validator->errors()->add(
                    'reported_user_id',
                    __('You cannot report your own account.'),
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
        return [
            'description.min' => 'Please describe what happened in at least 20 characters, so an administrator can act on it.',
        ];
    }
}
