<?php

namespace Tests\Feature\Matching;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Matching as each role actually meets it: a client typing what they want
 * built, and a student looking at the board.
 */
class MatchingScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClientModuleTaxonomySeeder::class);
    }

    public function test_a_scope_search_finds_the_student_who_can_build_it(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $capable = $this->student('Pia Reyes', ['payment-integration', 'mysql', 'php', 'laravel']);
        $partial = $this->student('Ana Lim', ['mysql']);
        $unrelated = $this->student('Ben Cruz', ['swift', 'spring-boot']);

        /*
         * Nobody is called "POS", so this used to return an empty page: the
         * words were matched against names. They describe work, so they rank.
         */
        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'search' => 'POS to my website system',
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($capable, $partial, $unrelated) {
                $students = collect($page->toArray()['props']['students']['data']);
                $ids = $students->pluck('id');

                $this->assertTrue($ids->contains($capable->user_id));
                $this->assertTrue($ids->contains($partial->user_id));
                $this->assertFalse(
                    $ids->contains($unrelated->user_id),
                    'A student with no skill this scope calls for is not a candidate.',
                );

                $this->assertSame(
                    $capable->user_id,
                    $students->first()['id'],
                    'Best fit leads the page.',
                );
                $this->assertGreaterThan(
                    $students->firstWhere('id', $partial->user_id)['compatibility'],
                    $students->first()['compatibility'],
                );
            });
    }

    public function test_the_search_says_which_skills_the_scope_needs(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $this->student('Pia Reyes', ['payment-integration']);

        // The half a percentage cannot answer: what am I shopping for?
        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'search' => 'POS for my store',
            ]))
            ->assertInertia(function (AssertableInertia $page) {
                $names = collect($page->toArray()['props']['scopeSkills'])->pluck('name');

                $this->assertTrue($names->contains('Payment Integration'));
                $this->assertTrue($names->contains('MySQL'));
            });
    }

    public function test_a_search_that_describes_nothing_gets_no_scores(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $this->student('Pia Reyes', ['mysql']);

        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'search' => 'Pia',
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', false)
                ->where('scopeSkills', []));
    }

    public function test_browsing_without_a_scope_shows_no_match_figures(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $this->student('Pia Reyes', ['mysql']);

        // Nothing has been asked for, so there is nothing to be compatible with.
        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', false)
                ->where('students.data.0.compatibility', null));
    }

    public function test_a_students_board_ranks_briefs_against_their_profile(): void
    {
        $student = User::factory()->student()->approved()->create();
        $profile = StudentProfile::factory()->for($student)->create(['is_available' => true]);
        $profile->skills()->sync(Skill::whereIn('slug', ['flutter', 'dart'])->pluck('id'));

        $this->brief('Delivery App', 'A mobile ordering app for a local canteen.');
        $this->brief('Payroll Ledger', 'An accounting and payroll ledger for a hardware supplier.');

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $briefs = collect($page->toArray()['props']['projects']['data']);

                $mobile = $briefs->firstWhere('title', 'Delivery App')['compatibility'];
                $payroll = $briefs->firstWhere('title', 'Payroll Ledger')['compatibility'];

                $this->assertGreaterThan(
                    $payroll,
                    $mobile,
                    'A Flutter student should rank the mobile brief above the payroll one.',
                );
            });
    }

    public function test_the_board_explains_why_a_brief_fits(): void
    {
        $student = User::factory()->student()->approved()->create();
        $profile = StudentProfile::factory()->for($student)->create(['is_available' => true]);
        $profile->skills()->sync(Skill::whereIn('slug', ['flutter', 'dart'])->pluck('id'));

        $this->brief('Delivery App', 'A mobile ordering app for a local canteen.');

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', true)
                ->whereNot('projects.data.0.insight', null)
                ->whereNot('highlight', null)
                ->has('highlight.factors'));
    }

    public function test_a_student_with_no_profile_is_not_scored(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->brief('Delivery App', 'A mobile ordering app for a local canteen.');

        // Nothing to match against yet, so the board stays plain rather than
        // inventing a fit.
        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', false)
                ->where('projects.data.0.compatibility', null));
    }

    /**
     * A student with a findable profile and the given skills.
     *
     * @param  list<string>  $skillSlugs
     */
    private function student(string $name, array $skillSlugs): StudentProfile
    {
        $user = User::factory()->student()->approved()->create(['name' => $name]);

        $profile = StudentProfile::factory()->for($user)->create([
            'is_available' => true,
            'rating_average' => 4.0,
            'completed_projects_count' => 2,
        ]);

        $profile->skills()->sync(Skill::whereIn('slug', $skillSlugs)->pluck('id'));

        return $profile;
    }

    /**
     * An open brief on the student board.
     *
     * School, course and year preferences are cleared on purpose. The factory
     * randomises them, and a preference match is worth enough that the noise
     * can outrank the thing these tests are actually measuring — whether the
     * words in the brief reached the right student.
     */
    private function brief(string $title, string $description): Project
    {
        return Project::factory()->create([
            'title' => $title,
            'description' => $description,
            'status' => ProjectStatus::Open,
            'applications_open' => true,
            /*
             * Everything the scope reads has to be pinned, not just the
             * description. ScopeProfile takes the title, category, industry
             * and objectives too, and the factory fills those with faker prose
             * that can infer skills of its own — which made this test pass
             * alone and fail in a full run.
             */
            'category' => 'Custom system',
            'industry' => null,
            'objectives' => null,
        ]);
    }
}
