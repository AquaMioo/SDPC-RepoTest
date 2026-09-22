<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DecideDeadlineChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the client decides a date, as only the client verifies work — the
     * side that is held to a deadline does not get to grant itself more time.
     * See AgreementPolicy::verifyTasks().
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('verifyTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
