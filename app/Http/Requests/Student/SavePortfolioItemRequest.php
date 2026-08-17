<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One entry in the Student Background History.
 *
 * Ownership is enforced by the controller resolving the item through the
 * signed-in student's profile, so a stray id 404s rather than reaching a row
 * belonging to somebody else.
 */
class SavePortfolioItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            /* Coursework goes back a few years; nothing is shipped in 1979. */
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'url' => ['nullable', 'url', 'max:255'],
            'repository_url' => ['nullable', 'url', 'max:255'],
            'is_featured' => ['boolean'],

            'skills' => ['array', 'max:12'],
            'skills.*' => ['required', 'string', 'max:60'],
        ];
    }

    /**
     * Get the human-readable names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'repository_url' => 'repository link',
            'url' => 'project link',
        ];
    }
}
