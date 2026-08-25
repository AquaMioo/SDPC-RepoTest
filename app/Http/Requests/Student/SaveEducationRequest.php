<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One school on the student's own profile.
 *
 * No policy: a student writes their own schooling and nobody else's, and the
 * controller resolves the row through the signed-in user rather than from the
 * request.
 */
class SaveEducationRequest extends FormRequest
{
    /** Nobody was at school before this, and nobody plans that far ahead. */
    private const EARLIEST_YEAR = 1950;

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
        $latest = (int) now()->year + 10;

        return [
            /*
             * Free text, because the lookup table does not hold every school
             * in the province and refusing an unlisted one would be refusing
             * the student. school_id below is the optional link back to the
             * list, set by the controller when the typed name matches.
             */
            'school' => ['required', 'string', 'max:255'],
            'school_id' => ['nullable', Rule::exists(School::class, 'id')],
            'course_id' => ['nullable', Rule::exists(Course::class, 'id')],

            'area_of_study' => ['nullable', 'string', 'max:255'],

            'from_year' => ['nullable', 'integer', 'min:'.self::EARLIEST_YEAR, 'max:'.$latest],
            /*
             * Allowed to equal from_year — a one-year programme is a real
             * thing — but never to precede it.
             */
            'to_year' => ['nullable', 'integer', 'min:'.self::EARLIEST_YEAR, 'max:'.$latest, 'gte:from_year'],

            'description' => ['nullable', 'string', 'max:2000'],
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
            'course_id' => 'degree',
            'from_year' => 'start year',
            'to_year' => 'end year',
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
            'to_year.gte' => 'The end year cannot come before the start year.',
        ];
    }
}
