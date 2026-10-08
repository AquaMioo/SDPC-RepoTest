<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('complete', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Completing is rating: the 1-5 rating is required and the feedback
     * optional (owner, 2026-10-09). Both are permanent once sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
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
            'rating.required' => __('Rate the work from 1 to 5 stars to complete the project.'),
            'rating.between' => __('A rating is from 1 to 5 stars.'),
        ];
    }
}
