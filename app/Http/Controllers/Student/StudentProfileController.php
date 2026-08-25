<?php

namespace App\Http\Controllers\Student;

use App\Enums\LanguageProficiency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveProfilePhotoRequest;
use App\Http\Requests\Student\UpdateStudentProfileRequest;
use App\Models\Barangay;
use App\Models\Course;
use App\Models\School;
use App\Models\Skill;
use App\Models\StudentEducation;
use App\Models\StudentLanguage;
use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own profile — Profile Background from the vision document.
 *
 * Until now a student could not touch any of this: `student_profiles` was
 * written by the seeder and read by the client module, which left the person
 * the row describes as the only party who could not correct it.
 */
class StudentProfileController extends Controller
{
    /**
     * Show the student their profile.
     */
    public function edit(Request $request, Team $currentTeam): Response
    {
        $profile = $this->profileFor($request->user());

        $profile->load([
            'skills',
            'portfolioItems.skills',
            'educations.course',
            'languages',
            'school',
            'course',
        ]);

        return Inertia::render('student/profile', [
            'profile' => [
                'name' => $request->user()->name,
                /*
                 * The account's own address, not a profile column. It is here
                 * because this screen is the only place a student can change
                 * their name or email — the settings screen carries no editor
                 * for either any more.
                 */
                'email' => $request->user()->email,
                'avatarUrl' => $request->user()->avatarUrl(),
                /* The line under the name: where they are, what they study. */
                'displayLocation' => $profile->displayLocation(),
                'schoolName' => $profile->school?->name,
                'courseAbbreviation' => $profile->course?->abbreviation ?? $profile->course?->name,
                'headline' => $profile->headline,
                'biography' => $profile->biography,
                'location' => $profile->location,
                'barangay' => $profile->barangay,

                'schoolId' => $profile->school_id,
                'courseId' => $profile->course_id,
                'yearLevel' => $profile->year_level,
                'educationStartedOn' => $profile->education_started_on?->toDateString(),
                'educationNote' => $profile->education_note,

                'githubUrl' => $profile->github_url,
                'portfolioUrl' => $profile->portfolio_url,

                'isAvailable' => $profile->is_available,
                'weeklyHours' => $profile->weekly_hours,
                'availabilityNote' => $profile->availability_note,
                'responseTimeHours' => $profile->response_time_hours,
                'hourlyRate' => $profile->hourly_rate,

                'skills' => $profile->skills->pluck('name')->values()->all(),
            ],
            'educations' => $profile->educations
                ->map(fn (StudentEducation $education): array => [
                    'id' => $education->id,
                    'school' => $education->school,
                    'courseId' => $education->course_id,
                    'areaOfStudy' => $education->area_of_study,
                    'fromYear' => $education->from_year,
                    'toYear' => $education->to_year,
                    'description' => $education->description,
                    /* Composed here so the card and the public view agree. */
                    'qualification' => $education->displayQualification(),
                    'years' => $education->displayYears(),
                ])
                ->values()
                ->all(),
            'languages' => $profile->languages
                ->map(fn (StudentLanguage $language): array => [
                    'id' => $language->id,
                    'name' => $language->name,
                    'proficiency' => $language->proficiency->value,
                    'proficiencyLabel' => $language->proficiency->label(),
                ])
                ->values()
                ->all(),
            'portfolio' => $profile->portfolioItems
                ->map(fn (StudentPortfolioItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'role' => $item->role,
                    'description' => $item->description,
                    'year' => $item->year,
                    'url' => $item->url,
                    'repositoryUrl' => $item->repository_url,
                    'isFeatured' => $item->is_featured,
                    'skills' => $item->skills->pluck('name')->values()->all(),
                ])
                ->values()
                ->all(),
            'options' => [
                'schools' => School::query()->orderBy('name')->get(['id', 'name']),
                'courses' => Course::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
                'skills' => Skill::query()->orderBy('name')->get(['name', 'type']),
                // The platform serves one city, so this is the whole list.
                'barangays' => Barangay::names(),
                'proficiencies' => LanguageProficiency::options(),
            ],
            /* What the skills dialog will not let a student go past. */
            'maximumSkills' => UpdateStudentProfileRequest::MAXIMUM_SKILLS,
            /*
             * The photo dialog states both of these to the student, so they
             * are read from the rules that actually reject the upload rather
             * than written into the copy where they could drift.
             */
            'photoLimits' => [
                'megabytes' => (int) round(config('uploads.max_image_kilobytes') / 1024),
                'pixels' => SaveProfilePhotoRequest::MINIMUM_PIXELS,
            ],
            /*
             * Badge presentation only. Whether a student may apply, message or
             * sign still answers to the administrator-reviewed credential —
             * see User::isVerifiedForOperating().
             */
            'isVerifiedStudent' => $request->user()->isVerifiedStudent(),
        ]);
    }

    /**
     * Save the profile.
     */
    public function update(UpdateStudentProfileRequest $request, Team $currentTeam): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        DB::transaction(function () use ($request, $profile): void {
            $profile->update($request->safe()->only([
                'headline', 'biography', 'location', 'barangay', 'school_id', 'course_id',
                'year_level', 'education_started_on', 'education_note',
                'github_url', 'portfolio_url', 'is_available', 'weekly_hours',
                'availability_note', 'response_time_hours', 'hourly_rate',
            ]));

            /*
             * Only when the submission actually carried skills. The profile is
             * edited a section at a time now, so a dialog saving nothing but a
             * headline posts no `skills` key at all — and syncing an absent
             * key against an empty array would silently strip every skill the
             * student had.
             */
            if ($request->has('skills')) {
                $profile->skills()->sync(Skill::idsForNames($request->array('skills')));
            }
        });

        return back()->with('success', 'Profile saved.');
    }

    /**
     * Get the signed-in student's profile, creating it on first visit.
     *
     * Registration does not mint one — this screen is where the row comes into
     * being, so a student who has never opened it is not a missing-model 404.
     */
    protected function profileFor(User $student): StudentProfile
    {
        return StudentProfile::firstOrCreate(['user_id' => $student->id]);
    }
}
