<?php

namespace Tests\Feature\Student;

use App\Enums\LanguageProficiency;
use App\Http\Requests\Student\SaveProfilePhotoRequest;
use App\Models\School;
use App\Models\Skill;
use App\Models\StudentEducation;
use App\Models\StudentLanguage;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The profile redesign: each card owns its own section and its own dialog.
 *
 * Every route here is pinned with an explicit current_team — UserFactory
 * switches the team it creates, so an incidental user from another factory
 * overwrites the URL default. See .ai/rules/feature.md.
 */
class StudentProfileSectionsTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------------
     | Education
     * ------------------------------------------------------------------ */

    public function test_a_student_lists_more_than_one_school(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->post($this->route('student.education.store', $student), [
                'school' => 'STI College San Jose Del Monte',
                'area_of_study' => 'Information Technology',
                'from_year' => 2022,
                'to_year' => 2026,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->post($this->route('student.education.store', $student), [
                'school' => 'Sapang Palay National High School',
                'area_of_study' => 'STEM strand',
                'from_year' => 2018,
                'to_year' => 2022,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, StudentEducation::count());
    }

    /**
     * The typed school is matched back to the lookup table so the client
     * recruit filters keep working, without the student having to know that
     * their school is also a row somewhere.
     */
    public function test_a_school_already_on_the_list_is_linked_to_it(): void
    {
        $student = $this->student();
        $school = School::factory()->create(['name' => 'STI College San Jose Del Monte']);

        $this->actingAs($student)
            ->post($this->route('student.education.store', $student), [
                // Typed in a different case than it is stored in.
                'school' => 'sti college san jose del monte',
            ]);

        $this->assertSame($school->id, StudentEducation::firstOrFail()->school_id);
    }

    public function test_a_school_nobody_has_listed_is_still_accepted(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->post($this->route('student.education.store', $student), [
                'school' => 'A barangay academy that is on no list',
            ])
            ->assertSessionHasNoErrors();

        $education = StudentEducation::firstOrFail();

        $this->assertSame('A barangay academy that is on no list', $education->school);
        $this->assertNull($education->school_id);
    }

    public function test_an_end_year_before_the_start_year_is_rejected(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->from($this->route('student.profile.edit', $student))
            ->post($this->route('student.education.store', $student), [
                'school' => 'STI College San Jose Del Monte',
                'from_year' => 2026,
                'to_year' => 2022,
            ])
            ->assertSessionHasErrors('to_year');

        $this->assertSame(0, StudentEducation::count());
    }

    public function test_a_student_edits_and_removes_their_own_schooling(): void
    {
        $student = $this->student();
        $education = StudentEducation::factory()->create([
            'student_profile_id' => $student->studentProfile->id,
        ]);

        $this->actingAs($student)
            ->patch($this->route('student.education.update', $student, ['education' => $education]), [
                'school' => 'A different school entirely',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('A different school entirely', $education->fresh()->school);

        $this->actingAs($student)
            ->delete($this->route('student.education.destroy', $student, ['education' => $education]));

        $this->assertSame(0, StudentEducation::count());
    }

    public function test_a_student_cannot_touch_somebody_elses_schooling(): void
    {
        $student = $this->student();
        $stranger = $this->student();

        $education = StudentEducation::factory()->create([
            'student_profile_id' => $stranger->studentProfile->id,
        ]);

        $this->actingAs($student)
            ->patch($this->route('student.education.update', $student, ['education' => $education]), [
                'school' => 'Rewriting a stranger past',
            ])
            ->assertNotFound();

        $this->actingAs($student)
            ->delete($this->route('student.education.destroy', $student, ['education' => $education]))
            ->assertNotFound();

        $this->assertSame(1, StudentEducation::count());
    }

    /**
     * "(expected)" is derived from the year still being ahead, so a graduation
     * stops being expected without anybody editing the row.
     */
    public function test_a_graduation_year_still_ahead_reads_as_expected(): void
    {
        $ongoing = StudentEducation::factory()->ongoing()->create();
        $finished = StudentEducation::factory()->finished()->create();

        $this->assertStringContainsString('(expected)', (string) $ongoing->displayYears());
        $this->assertStringNotContainsString('(expected)', (string) $finished->displayYears());
    }

    /* ---------------------------------------------------------------------
     | Languages
     * ------------------------------------------------------------------ */

    public function test_a_student_lists_a_language(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->post($this->route('student.languages.store', $student), [
                'name' => 'Tagalog',
                'proficiency' => LanguageProficiency::NativeOrBilingual->value,
            ])
            ->assertSessionHasNoErrors();

        $language = StudentLanguage::firstOrFail();

        $this->assertSame('Tagalog', $language->name);
        $this->assertSame(LanguageProficiency::NativeOrBilingual, $language->proficiency);
    }

    /**
     * The same language twice at two levels reads as a mistake, not a claim.
     */
    public function test_the_same_language_cannot_be_listed_twice(): void
    {
        $student = $this->student();

        StudentLanguage::factory()->create([
            'student_profile_id' => $student->studentProfile->id,
            'name' => 'Tagalog',
        ]);

        $this->actingAs($student)
            ->from($this->route('student.profile.edit', $student))
            ->post($this->route('student.languages.store', $student), [
                'name' => 'Tagalog',
                'proficiency' => LanguageProficiency::Elementary->value,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, StudentLanguage::count());
    }

    public function test_a_language_can_keep_its_name_while_its_level_changes(): void
    {
        $student = $this->student();

        $language = StudentLanguage::factory()->create([
            'student_profile_id' => $student->studentProfile->id,
            'name' => 'English',
            'proficiency' => LanguageProficiency::Elementary,
        ]);

        // The unique rule has to ignore the row being edited, or raising your
        // own level trips over your own name.
        $this->actingAs($student)
            ->patch($this->route('student.languages.update', $student, ['language' => $language]), [
                'name' => 'English',
                'proficiency' => LanguageProficiency::Professional->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(LanguageProficiency::Professional, $language->fresh()->proficiency);
    }

    public function test_a_student_cannot_touch_somebody_elses_languages(): void
    {
        $student = $this->student();
        $stranger = $this->student();

        $language = StudentLanguage::factory()->create([
            'student_profile_id' => $stranger->studentProfile->id,
        ]);

        $this->actingAs($student)
            ->delete($this->route('student.languages.destroy', $student, ['language' => $language]))
            ->assertNotFound();

        $this->assertSame(1, StudentLanguage::count());
    }

    /* ---------------------------------------------------------------------
     | Photo
     * ------------------------------------------------------------------ */

    public function test_a_student_sets_and_removes_their_photo(): void
    {
        Storage::fake('public');

        $student = $this->student();

        $this->actingAs($student)
            ->post($this->route('student.photo.update', $student), [
                'photo' => UploadedFile::fake()->image('me.jpg', 600, 600),
            ])
            ->assertSessionHasNoErrors();

        $path = $student->fresh()->avatar_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($student)
            ->delete($this->route('student.photo.destroy', $student));

        $this->assertNull($student->fresh()->avatar_path);
        // The file goes with the record, rather than being orphaned on disk.
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * The dialog promises "at least 400×400", so something has to hold it to
     * that or the promise is decoration.
     */
    public function test_a_photo_smaller_than_the_stated_minimum_is_refused(): void
    {
        Storage::fake('public');

        $student = $this->student();
        $short = SaveProfilePhotoRequest::MINIMUM_PIXELS - 1;

        $this->actingAs($student)
            ->from($this->route('student.profile.edit', $student))
            ->post($this->route('student.photo.update', $student), [
                'photo' => UploadedFile::fake()->image('tiny.jpg', $short, $short),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($student->fresh()->avatar_path);
    }

    /**
     * Removing an uploaded photo has to clear the Google-supplied URL too, or
     * "Remove photo" quietly restores the picture Google gave us.
     */
    public function test_removing_the_photo_also_drops_a_google_supplied_one(): void
    {
        Storage::fake('public');

        $student = $this->student();
        $student->forceFill(['avatar' => 'https://lh3.googleusercontent.com/a/portrait'])->save();

        $this->actingAs($student)
            ->delete($this->route('student.photo.destroy', $student));

        $this->assertNull($student->fresh()->avatarUrl());
    }

    /* ---------------------------------------------------------------------
     | Skills, and what a per-section save must not do
     * ------------------------------------------------------------------ */

    /**
     * The profile is saved a section at a time now, so a dialog that carries
     * no `skills` key must leave the skills alone. Syncing an absent key
     * against an empty array silently emptied the list.
     */
    public function test_saving_one_section_does_not_wipe_the_skills(): void
    {
        $student = $this->student();
        $profile = $student->studentProfile;

        $profile->skills()->sync(Skill::idsForNames(['Laravel', 'React']));

        $this->actingAs($student)
            ->patch($this->route('student.profile.update', $student), [
                'headline' => 'Only the headline changed here',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Laravel', 'React'],
            $profile->fresh()->skills->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_the_skills_dialog_still_replaces_the_list_when_it_sends_one(): void
    {
        $student = $this->student();
        $profile = $student->studentProfile;

        $profile->skills()->sync(Skill::idsForNames(['Laravel', 'React']));

        $this->actingAs($student)
            ->patch($this->route('student.profile.update', $student), [
                'skills' => ['Laravel', 'Flutter'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Flutter', 'Laravel'],
            $profile->fresh()->skills->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_more_skills_than_the_dialog_allows_are_refused(): void
    {
        $student = $this->student();

        $tooMany = array_map(
            fn (int $index): string => 'Skill '.$index,
            range(1, 21),
        );

        $this->actingAs($student)
            ->from($this->route('student.profile.edit', $student))
            ->patch($this->route('student.profile.update', $student), ['skills' => $tooMany])
            ->assertSessionHasErrors('skills');
    }

    /* ---------------------------------------------------------------------
     | The screen itself
     * ------------------------------------------------------------------ */

    public function test_the_screen_carries_the_sections_it_now_draws(): void
    {
        $student = $this->student();
        $profile = $student->studentProfile;

        StudentEducation::factory()->create(['student_profile_id' => $profile->id]);
        StudentLanguage::factory()->create(['student_profile_id' => $profile->id]);

        $this->actingAs($student)
            ->get($this->route('student.profile.edit', $student))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/profile')
                ->has('educations', 1)
                ->has('languages', 1)
                ->has('options.proficiencies', count(LanguageProficiency::cases()))
                ->where('maximumSkills', 20)
                ->where('photoLimits.pixels', SaveProfilePhotoRequest::MINIMUM_PIXELS));
    }

    /* ---------------------------------------------------------------------
     | The account behind the profile
     * ------------------------------------------------------------------ */

    /**
     * Name and email live on the user row and the settings screen no longer
     * edits them, so the profile screen has to carry the address or a student
     * cannot change what they sign in with anywhere on the platform.
     */
    public function test_the_screen_carries_the_account_email(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get($this->route('student.profile.edit', $student))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('profile.name', $student->name)
                ->where('profile.email', $student->email)
                ->etc());
    }

    public function test_a_student_changes_their_own_name_and_email(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'name' => 'Jeremie G. Caasi',
                'email' => 'jeremie@sdpc.test',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $student->fresh();

        $this->assertSame('Jeremie G. Caasi', $fresh->name);
        $this->assertSame('jeremie@sdpc.test', $fresh->email);
        // A new address is not a proved one.
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_an_address_somebody_else_holds_is_refused(): void
    {
        $student = $this->student();
        $taken = $this->student();

        $this->actingAs($student)
            ->from($this->route('student.profile.edit', $student))
            ->patch(route('profile.update'), [
                'name' => $student->name,
                'email' => $taken->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($student->email, $student->fresh()->email);
    }

    /**
     * A student with a profile row already in place.
     */
    private function student(): User
    {
        $student = User::factory()->student()->create();

        StudentProfile::factory()->create(['user_id' => $student->id]);

        return $student->fresh();
    }

    /**
     * Build a team-scoped route for the given student.
     *
     * @param  array<string, mixed>  $extra
     */
    private function route(string $name, User $student, array $extra = []): string
    {
        return route($name, ['current_team' => $student->currentTeam, ...$extra]);
    }
}
