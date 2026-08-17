<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The student's own profile.
 *
 * There is no policy here because there is nothing to decide: a student edits
 * their profile and nobody else's, and the controller resolves the row from
 * the signed-in user rather than from the request.
 */
class UpdateStudentProfileRequest extends FormRequest
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
            'headline' => ['nullable', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],

            'school_id' => ['nullable', Rule::exists(School::class, 'id')],
            'course_id' => ['nullable', Rule::exists(Course::class, 'id')],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'education_started_on' => ['nullable', 'date'],
            'education_note' => ['nullable', 'string', 'max:2000'],

            'github_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],

            'is_available' => ['boolean'],
            /* A tiny integer column, and nobody is free for 300 hours a week. */
            'weekly_hours' => ['nullable', 'integer', 'min:1', 'max:80'],
            'availability_note' => ['nullable', 'string', 'max:255'],
            'response_time_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'hourly_rate' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'skills' => ['array', 'max:30'],
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
            'school_id' => 'school',
            'course_id' => 'course',
            'weekly_hours' => 'hours per week',
            'response_time_hours' => 'response time',
        ];
    }
}
