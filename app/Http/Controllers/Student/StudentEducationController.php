<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveEducationRequest;
use App\Models\School;
use App\Models\StudentEducation;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The schools a student went to.
 *
 * Every method resolves the row through the signed-in student's own profile,
 * the same way PortfolioItemController does, so there is no policy to forget:
 * an id belonging to somebody else simply is not found.
 */
class StudentEducationController extends Controller
{
    /**
     * Add a school.
     */
    public function store(SaveEducationRequest $request, Team $currentTeam): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $profile->educations()->create($this->attributes($request));

        return back()->with('success', 'Education added.');
    }

    /**
     * Edit a school.
     */
    public function update(
        SaveEducationRequest $request,
        Team $currentTeam,
        StudentEducation $education,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $education);

        $education->update($this->attributes($request));

        return back()->with('success', 'Education updated.');
    }

    /**
     * Remove a school.
     */
    public function destroy(
        Request $request,
        Team $currentTeam,
        StudentEducation $education,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $education);

        $education->delete();

        return back()->with('success', 'Education removed.');
    }

    /**
     * Get the columns a submission writes.
     *
     * @return array<string, mixed>
     */
    protected function attributes(SaveEducationRequest $request): array
    {
        $school = $request->string('school')->trim()->toString();

        return [
            'school' => $school,
            /*
             * The link back to the list a client filters on. Resolved from
             * what was typed rather than asked for separately: a student
             * should not have to know that their school is also a row in a
             * lookup table, and one that is not stays free text.
             */
            'school_id' => $this->listedSchoolId($school),
            'course_id' => $request->input('course_id'),
            'area_of_study' => $request->input('area_of_study'),
            'from_year' => $request->input('from_year'),
            'to_year' => $request->input('to_year'),
            'description' => $request->input('description'),
        ];
    }

    /**
     * Match a typed school name against the list, if it is on it.
     */
    protected function listedSchoolId(string $school): ?int
    {
        return School::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($school)])
            ->value('id');
    }

    /**
     * Refuse a row that is not this student's.
     */
    protected function authorizeOwnership(User $student, StudentEducation $education): void
    {
        abort_unless(
            $education->student_profile_id === $student->studentProfile?->id,
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
