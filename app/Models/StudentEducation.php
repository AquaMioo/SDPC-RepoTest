<?php

namespace App\Models;

use Database\Factories\StudentEducationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One school a student went to.
 *
 * @property int $id
 * @property int $student_profile_id
 * @property string $school
 * @property int|null $school_id
 * @property int|null $course_id
 * @property string|null $area_of_study
 * @property int|null $from_year
 * @property int|null $to_year
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudentProfile $studentProfile
 * @property-read School|null $schoolRecord
 * @property-read Course|null $course
 */
#[Fillable([
    'student_profile_id', 'school', 'school_id', 'course_id',
    'area_of_study', 'from_year', 'to_year', 'description',
])]
class StudentEducation extends Model
{
    /** @use HasFactory<StudentEducationFactory> */
    use HasFactory;

    /**
     * Named explicitly because "education" is uncountable to the inflector,
     * which guesses `student_education` and then cannot find the table.
     *
     * @var string
     */
    protected $table = 'student_educations';

    /**
     * Get the profile this schooling belongs to.
     *
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * Get the listed school this was matched to, if it matched one.
     *
     * Named around the column rather than `school()`, which is already the
     * free-text string a student typed.
     *
     * @return BelongsTo<School, $this>
     */
    public function schoolRecord(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Get the degree, where one was picked off the list.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The line under the school name: degree and field, as one reads it.
     *
     * "BSIT — Information Technology" when both are known, either one alone
     * when only one is, and nothing at all rather than a stray dash.
     */
    public function displayQualification(): ?string
    {
        $parts = array_filter([
            $this->course?->abbreviation ?? $this->course?->name,
            $this->area_of_study,
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /**
     * The years, with the end marked as expected while it still is.
     *
     * Derived rather than stored: a graduation year that has passed stops
     * being expected without anybody editing the row.
     */
    public function displayYears(): ?string
    {
        if ($this->from_year === null && $this->to_year === null) {
            return null;
        }

        if ($this->to_year === null) {
            return (string) $this->from_year;
        }

        $end = $this->to_year > (int) now()->year
            ? $this->to_year.' (expected)'
            : (string) $this->to_year;

        return $this->from_year === null ? $end : $this->from_year.' – '.$end;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_year' => 'integer',
            'to_year' => 'integer',
        ];
    }
}
