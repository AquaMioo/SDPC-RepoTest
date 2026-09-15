<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderTasksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('manageTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Whether the ids are exactly this phase's tasks is checked in the
     * controller, which has the phase in hand.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct'],
        ];
    }
}
