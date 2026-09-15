<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReturnTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the client reviews, so only the client sends work back.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('verifyTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The note is required: a task sent back with no reason leaves the student
     * guessing what to fix.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:2000'],
        ];
    }
}
