<?php

namespace App\Models;

use App\Enums\LanguageProficiency;
use Database\Factories\StudentLanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One language a student says they can work in.
 *
 * @property int $id
 * @property int $student_profile_id
 * @property string $name
 * @property LanguageProficiency $proficiency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudentProfile $studentProfile
 */
#[Fillable(['student_profile_id', 'name', 'proficiency'])]
class StudentLanguage extends Model
{
    /** @use HasFactory<StudentLanguageFactory> */
    use HasFactory;

    /**
     * Get the profile this language belongs to.
     *
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proficiency' => LanguageProficiency::class,
        ];
    }
}
