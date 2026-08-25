<?php

namespace App\Http\Controllers\Student;

use App\Enums\LanguageProficiency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveLanguageRequest;
use App\Models\StudentLanguage;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The languages a student can work in.
 *
 * Resolved through the signed-in student's own profile, like the rest of this
 * module — somebody else's row is a 404 rather than a policy question.
 */
class StudentLanguageController extends Controller
{
    /**
     * Add a language.
     */
    public function store(SaveLanguageRequest $request, Team $currentTeam): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $profile->languages()->create($this->attributes($request));

        return back()->with('success', 'Language added.');
    }

    /**
     * Change a language, or the level claimed for it.
     */
    public function update(
        SaveLanguageRequest $request,
        Team $currentTeam,
        StudentLanguage $language,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $language);

        $language->update($this->attributes($request));

        return back()->with('success', 'Language updated.');
    }

    /**
     * Remove a language.
     */
    public function destroy(
        Request $request,
        Team $currentTeam,
        StudentLanguage $language,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $language);

        $language->delete();

        return back()->with('success', 'Language removed.');
    }

    /**
     * Get the columns a submission writes.
     *
     * @return array<string, mixed>
     */
    protected function attributes(SaveLanguageRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'proficiency' => LanguageProficiency::from($request->string('proficiency')->toString()),
        ];
    }

    /**
     * Refuse a row that is not this student's.
     */
    protected function authorizeOwnership(User $student, StudentLanguage $language): void
    {
        abort_unless(
            $language->student_profile_id === $student->studentProfile?->id,
            404,
        );
    }

    /**
     * Get the signed-in student's profile, creating it on first visit.
     */
    protected function profileFor(User $student): StudentProfile
    {
        return StudentProfile::firstOrCreate(['user_id' => $student->id]);
    }
}
