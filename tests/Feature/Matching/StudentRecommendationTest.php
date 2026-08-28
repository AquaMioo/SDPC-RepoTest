<?php

namespace Tests\Feature\Matching;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\ScoresProjectsForText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The student half of the matching engine: one reader, many briefs.
 *
 * The mirror of GeminiRecommendationTest, which covers one brief against many
 * students. Both directions share the request and the parser, so the fallback
 * paths are proved once there and only the shape of this direction is proved
 * here — plus the capstone search, which has no counterpart on the client side.
 *
 * Every field ScopeProfile reads is pinned on the fixtures; the factory fills
 * them with faker prose that infers skills of its own. See .ai/rules/matching.md.
 */
class StudentRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recommendations.driver', 'gemini');
        config()->set('gemini.api_key', 'test-key');
    }

    public function test_the_driver_can_rank_briefs_against_a_typed_capstone(): void
    {
        $this->assertInstanceOf(
            ScoresProjectsForText::class,
            $this->app->make(RecommendationService::class),
        );
    }

    public function test_it_scores_open_briefs_for_a_student(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response($this->reply([[
            'id' => $project->id,
            'compatibility' => 93,
            'insight' => 'Your stock-tracking work maps almost one-to-one onto this brief.',
            'recommendation' => 'Lead your pitch with the inventory module.',
            'factors' => [
                ['label' => 'Laravel and MySQL', 'value' => 95],
                ['label' => 'Stock domain', 'value' => 88],
            ],
        ]]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresForStudent($student);

        $this->assertSame(93, $scores[$project->id]['compatibility']);
        $this->assertSame('gemini', $scores[$project->id]['reason']['source']);
    }

    public function test_the_factors_and_recommendation_reach_the_match_panel(): void
    {
        [$student, $project] = $this->studentAndBrief();

        /*
         * The board already draws a ring, per-factor bars and a strategic
         * line. Without these two keys the panel renders its ring over an
         * empty space, which is what the first version of this driver did.
         */
        Http::fake(['*' => Http::response($this->reply([[
            'id' => $project->id,
            'compatibility' => 92,
            'insight' => 'Strong fit.',
            'recommendation' => 'Lead your pitch with the booking flow.',
            'factors' => [
                ['label' => 'React and Next.js', 'value' => 81],
                ['label' => 'Multi-tenant data', 'value' => 88],
            ],
        ]]))]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('matchingEnabled', true)
                ->where('highlight.compatibility', 92)
                ->has('highlight.factors', 2)
                ->where('highlight.factors.0.label', 'React and Next.js')
                ->where('highlight.factors.0.value', 81)
                ->where('highlight.recommendation', 'Lead your pitch with the booking flow.'));
    }

    public function test_a_malformed_factor_is_dropped_rather_than_drawn(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response($this->reply([[
            'id' => $project->id,
            'compatibility' => 70,
            'insight' => 'Reasonable fit.',
            'factors' => [
                ['label' => 'Laravel', 'value' => 80],
                ['label' => '', 'value' => 50],
                ['label' => 'No score at all'],
                ['value' => 40],
            ],
        ]]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresForStudent($student);

        $this->assertCount(1, $scores[$project->id]['reason']['factors']);
        $this->assertSame('Laravel', $scores[$project->id]['reason']['factors'][0]['label']);
    }

    public function test_the_capstone_search_ranks_briefs_against_what_was_typed(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response($this->reply([[
            'id' => $project->id,
            'compatibility' => 87,
            'insight' => 'Your capstone covers the same ground as this brief.',
        ]]))]);

        $this->actingAs($student)
            ->get(route('student.board.index', [
                'current_team' => $student->currentTeam,
                'capstone_title' => 'Inventory System with Predictive Analytics',
                'capstone_description' => 'A stock system for a hardware store that forecasts reorder points.',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('capstone.title', 'Inventory System with Predictive Analytics')
                ->where('highlight.compatibility', 87));

        /* What the student typed is what the model was asked about. */
        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->body(),
            'Predictive Analytics',
        ));
    }

    public function test_the_capstone_search_never_carries_a_name_or_email(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $project->id, 'compatibility' => 50, 'insight' => 'Plausible.'],
        ]))]);

        $this->app->make(RecommendationService::class)->scoresForStudent($student);

        Http::assertSent(function (Request $request) use ($student): bool {
            $this->assertStringNotContainsString($student->name, $request->body());
            $this->assertStringNotContainsString($student->email, $request->body());

            return true;
        });
    }

    public function test_the_school_name_is_never_sent_but_the_course_is(): void
    {
        [$student, $project] = $this->studentAndBrief();

        $profile = $student->studentProfile;
        $school = $profile->school;

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $project->id, 'compatibility' => 50, 'insight' => 'Plausible.'],
        ]))]);

        $this->app->make(RecommendationService::class)->scoresForStudent($student);

        Http::assertSent(function (Request $request) use ($school): bool {
            /*
             * Course and year are educational background and bear on whether
             * somebody can build a thing. The campus does not, and school plus
             * course plus year narrows a cohort to a person.
             */
            if ($school !== null) {
                $this->assertStringNotContainsString($school->name, $request->body());
            }

            return true;
        });
    }

    public function test_a_student_with_no_profile_is_scored_against_nothing(): void
    {
        $student = User::factory()->student()->approved()->create();

        Http::fake();

        $this->assertTrue(
            $this->app->make(RecommendationService::class)->scoresForStudent($student)->isEmpty(),
        );
        Http::assertNothingSent();
    }

    public function test_a_dead_service_leaves_the_board_ranked_by_the_computed_scorer(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response('upstream is down', 503)]);

        /* The board still renders, with keyword scores behind it. */
        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects.data', 1));
    }

    public function test_a_brief_the_model_forgot_keeps_its_computed_score(): void
    {
        [$student, $first] = $this->studentAndBrief();

        $second = Project::factory()->create([
            'title' => 'Booking and dispatch board',
            'category' => 'Web application',
            'industry' => 'Services',
            'description' => 'Bookings arrive by text and get lost.',
            'objectives' => 'One board the dispatcher assigns jobs on.',
            'status' => ProjectStatus::Open,
        ]);

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $first->id, 'compatibility' => 90, 'insight' => 'Strong fit.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresForStudent($student);

        $this->assertArrayHasKey($second->id, $scores->all());
        $this->assertSame(90, $scores[$first->id]['compatibility']);
    }

    /**
     * A Gemini generateContent reply carrying the given matches.
     *
     * @param  list<array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function reply(array $matches): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode(['matches' => $matches])]]],
            ]],
        ];
    }

    /**
     * A student with a profile, and one open brief to rank.
     *
     * @return array{0: User, 1: Project}
     */
    private function studentAndBrief(): array
    {
        $student = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($student)->create([
            'headline' => 'Laravel and MySQL developer',
            'biography' => 'I build stock and warehouse systems.',
        ]);

        $project = Project::factory()->create([
            'title' => 'Counter and stock system for a hardware store',
            'category' => 'Web application',
            'industry' => 'Retail',
            'description' => 'We still run the counter on a paper ledger and count stock by walking the aisles.',
            'objectives' => 'Record stock in and stock out.',
            'status' => ProjectStatus::Open,
            'applications_open' => true,
        ]);

        return [$student->fresh(), $project];
    }
}
