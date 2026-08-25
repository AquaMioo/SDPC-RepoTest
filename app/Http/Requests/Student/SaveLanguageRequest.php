<?php

namespace App\Http\Requests\Student;

use App\Enums\LanguageProficiency;
use App\Enums\UserRole;
use App\Models\StudentLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One language on the student's own profile.
 */
class SaveLanguageRequest extends FormRequest
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
        /*
         * Scoped to this student and ignoring the row being edited, so the
         * same language cannot appear twice at two levels — which reads as a
         * mistake rather than as a claim — while renaming nothing about a row
         * still trips over itself. Mirrors the unique index on the table.
         */
        $unique = Rule::unique(StudentLanguage::class, 'name')
            ->where('student_profile_id', $this->user()?->studentProfile?->id);

        $language = $this->route('language');

        if ($language instanceof StudentLanguage) {
            $unique->ignore($language->id);
        }

        return [
            'name' => ['required', 'string', 'max:60', $unique],
            'proficiency' => ['required', Rule::enum(LanguageProficiency::class)],
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
            'name.unique' => 'That language is already on your profile.',
        ];
    }
}
