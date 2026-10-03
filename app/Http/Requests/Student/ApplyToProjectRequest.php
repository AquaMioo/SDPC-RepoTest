<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyToProjectRequest extends FormRequest
{
    /**
     * The fewest words a cover letter may have. resources/js/pages/student/project.tsx counts the same.
     */
    public const MIN_WORDS = 5;

    /**
     * The most words a cover letter may have.
     */
    public const MAX_WORDS = 50;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route already carries the student and verification middleware; this
     * is the belt to that pair of braces.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The letter is measured in words, not characters: 5 to 50. The
     * character ceiling stays only as a guard on what is stored.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cover_letter' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $words = self::wordCount((string) $value);

                    if ($words < self::MIN_WORDS) {
                        $fail(__('Give the client something to read — at least :min words.', ['min' => self::MIN_WORDS]));
                    } elseif ($words > self::MAX_WORDS) {
                        $fail(__('Keep it to :max words or fewer. This one has :count.', ['max' => self::MAX_WORDS, 'count' => $words]));
                    }
                },
            ],
        ];
    }

    /**
     * Count the words in a piece of text: runs of anything but whitespace.
     */
    public static function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cover_letter.required' => 'Tell the client why you are a fit.',
        ];
    }
}
