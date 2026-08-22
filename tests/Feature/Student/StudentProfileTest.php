<?php

namespace Tests\Feature\Student;

use App\Models\School;
use App\Models\Skill;
use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A student owns their own profile, and the portfolio behind it.
 */
class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_screen_opens_for_a_student_who_has_never_had_one(): void
    {
        $student = User::factory()->student()->create();

        $this->assertNull($student->studentProfile);

        $this->actingAs($student)
            ->get(route('student.profile.edit', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('student/profile')
                ->where('profile.name', $student->name)
                ->has('portfolio', 0));

        // The screen is where the row comes into being, so opening it is not a
        // 404 for the one person entitled to write it.
        $this->assertNotNull($student->refresh()->studentProfile);
    }

    public function test_a_client_cannot_reach_the_student_profile_screen(): void
    {
        $client = User::factory()->verifiedBusiness()->create();

        $this->actingAs($client)
            ->get(route('student.profile.edit', ['current_team' => $client->currentTeam]))
            ->assertForbidden();
    }

    public function test_a_student_picks_a_barangay_and_types_the_rest(): void
    {
        $student = User::factory()->student()->create();
        $this->seed(ClientModuleTaxonomySeeder::class);

        $this->actingAs($student)
            ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                'headline' => 'Full-stack developer',
                'barangay' => 'Muzon',
                'location' => 'Towerville, Phase 2',
            ])
            ->assertSessionHasNoErrors();

        $profile = $student->refresh()->studentProfile;

        $this->assertSame('Muzon', $profile->barangay);
        $this->assertSame('Towerville, Phase 2', $profile->location);
        $this->assertSame(
            'Towerville, Phase 2, Barangay Muzon, San Jose Del Monte',
            $profile->displayLocation(),
        );
    }

    /**
     * The free-text half is the escape hatch for addresses no list holds, so
     * it has to stand on its own.
     */
    public function test_the_area_may_be_given_without_a_barangay(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                'headline' => 'Full-stack developer',
                'location' => 'A subdivision no map knows',
            ])
            ->assertSessionHasNoErrors();

        $profile = $student->refresh()->studentProfile;

        $this->assertNull($profile->barangay);
        $this->assertSame('A subdivision no map knows', $profile->displayLocation());
    }

    public function test_a_barangay_that_is_not_on_the_list_is_rejected(): void
    {
        $student = User::factory()->student()->create();
        $this->seed(ClientModuleTaxonomySeeder::class);

        $this->actingAs($student)
            ->from(route('student.profile.edit', ['current_team' => $student->currentTeam]))
            ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                'headline' => 'Full-stack developer',
                'barangay' => 'Somewhere Else',
            ])
            ->assertSessionHasErrors('barangay');
    }

    /**
     * College runs four years. The form offers 1 to 4, and this rule is what
     * holds if something is posted around it.
     */
    public function test_a_year_level_outside_the_four_is_rejected(): void
    {
        $student = User::factory()->student()->create();

        foreach ([5, 6, 0] as $rejected) {
            $this->actingAs($student)
                ->from(route('student.profile.edit', ['current_team' => $student->currentTeam]))
                ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                    'headline' => 'Full-stack developer',
                    'year_level' => $rejected,
                ])
                ->assertSessionHasErrors('year_level');
        }
    }

    public function test_each_of_the_four_year_levels_is_accepted(): void
    {
        $student = User::factory()->student()->create();

        foreach ([1, 2, 3, 4] as $level) {
            $this->actingAs($student)
                ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                    'headline' => 'Full-stack developer',
                    'year_level' => $level,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame($level, $student->refresh()->studentProfile->year_level);
        }
    }

    public function test_a_student_saves_their_own_profile(): void
    {
        $student = User::factory()->student()->create();
        $school = School::factory()->create();

        $this->actingAs($student)
            ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                'headline' => 'Full-stack developer · Laravel and React',
                'biography' => 'Fourth-year IT student building web systems for small businesses in Bulacan.',
                'location' => 'Towerville, San Jose del Monte',
                'school_id' => $school->id,
                'year_level' => 4,
                'is_available' => true,
                'weekly_hours' => 20,
                'hourly_rate' => 260,
                'response_time_hours' => 3,
                'skills' => ['Laravel', 'React', 'MySQL'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = $student->refresh()->studentProfile;

        $this->assertSame('Towerville, San Jose del Monte', $profile->location);
        $this->assertSame(20, $profile->weekly_hours);
        $this->assertSame($school->id, $profile->school_id);
        $this->assertEqualsCanonicalizing(
            ['Laravel', 'React', 'MySQL'],
            $profile->skills->pluck('name')->all(),
        );
    }

    public function test_saving_the_same_skill_in_another_case_does_not_mint_a_second_row(): void
    {
        $student = User::factory()->student()->create();

        Skill::findOrCreateByName('Laravel');

        $this->actingAs($student)
            ->patch(route('student.profile.update', ['current_team' => $student->currentTeam]), [
                'skills' => ['laravel'],
            ])
            ->assertRedirect();

        $this->assertSame(1, Skill::query()->where('slug', 'laravel')->count());
    }

    public function test_a_student_adds_a_piece_of_work(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('student.portfolio.store', ['current_team' => $student->currentTeam]), [
                'title' => 'Grosync',
                'role' => 'Lead developer',
                'description' => 'Inventory management with predictive reorder analytics.',
                'year' => 2025,
                'repository_url' => 'https://github.com/example/grosync',
                'is_featured' => true,
                'skills' => ['Laravel', 'MySQL'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = $student->refresh()->studentProfile->portfolioItems->firstOrFail();

        $this->assertSame('Grosync', $item->title);
        $this->assertEqualsCanonicalizing(
            ['Laravel', 'MySQL'],
            $item->skills->pluck('name')->all(),
        );
    }

    public function test_a_student_cannot_touch_somebody_elses_portfolio(): void
    {
        $intruder = User::factory()->student()->create();

        $item = StudentPortfolioItem::factory()->create();

        $this->actingAs($intruder)
            ->patch(route('student.portfolio.update', [
                'current_team' => $intruder->currentTeam,
                'portfolioItem' => $item,
            ]), ['title' => 'Mine now'])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->delete(route('student.portfolio.destroy', [
                'current_team' => $intruder->currentTeam,
                'portfolioItem' => $item,
            ]))
            ->assertNotFound();

        $this->assertNotSame('Mine now', $item->refresh()->title);
    }

    public function test_a_student_removes_a_piece_of_work(): void
    {
        $student = User::factory()->student()->create();
        $profile = StudentProfile::factory()->create(['user_id' => $student->id]);

        $item = StudentPortfolioItem::factory()->create([
            'student_profile_id' => $profile->id,
        ]);

        $this->actingAs($student)
            ->delete(route('student.portfolio.destroy', [
                'current_team' => $student->currentTeam,
                'portfolioItem' => $item,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('student_portfolio_items', ['id' => $item->id]);
    }

    public function test_the_client_facing_profile_shows_the_portfolio(): void
    {
        $client = User::factory()->verifiedBusiness()->create();

        $student = User::factory()->student()->create();
        $profile = StudentProfile::factory()->create(['user_id' => $student->id]);

        $item = StudentPortfolioItem::factory()->create([
            'student_profile_id' => $profile->id,
            'title' => 'Grosync',
        ]);

        $item->skills()->sync(Skill::idsForNames(['Laravel']));

        $this->actingAs($client)
            ->get(route('students.show', [
                'current_team' => $client->currentTeam,
                'user' => $student,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('student.portfolio', 1)
                ->where('student.portfolio.0.title', 'Grosync')
                ->where('student.portfolio.0.skills', ['Laravel']));
    }
}
