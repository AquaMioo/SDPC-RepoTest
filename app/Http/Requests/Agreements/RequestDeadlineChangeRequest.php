<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RequestDeadlineChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Asking is the student side's — the signer and their teammates, the
     * people who work to the deadline. See AgreementPolicy::manageTasks().
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('manageTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The rules that depend on the other dates — before the final deadline,
     * after the latest task — live in RequestDeadlineChange, which checks them
     * under a lock.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proposed_on' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'proposed_on.required' => __('Choose the date you are asking for.'),
        ];
    }
}
