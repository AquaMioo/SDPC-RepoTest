<?php

namespace Tests\Feature\Matching;

use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Recommendation\ComputedRecommendationService;
use App\Services\Recommendation\GeminiRecommendationService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\ScoresFreeText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The model reads the descriptions; the computed scorer catches it when it
 * cannot.
 *
 * Almost every test here is about the falling back rather than the matching.
 * That is the point: matching is on the critical path of the whole product, so
 * the interesting question is never "does the model give a good answer" — it
 * is "what does a client see at the exact moment Google does not answer at
 * all". The answer has to be: the same screen as always, and no idea anything
 * happened.
 *
 * @see .ai/rules/matching.md for why every scored field is pinned
 */
class GeminiRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recommendations.driver', 'gemini');
        config()->set('gemini.api_key', 'test-key');
    }

    public function test_the_driver_is_selected_by_configuration(): void
    {
        $this->assertInstanceOf(
            GeminiRecommendationService::class,
            $this->app->make(RecommendationService::class),
        );
    }

    public function test_it_can_answer_free_text_so_the_recruit_search_still_ranks(): void
    {
        /*
         * RecruitController decides whether the search box ranks or merely
         * filters by testing for this contract. Without it, typing "POS to my
         * website system" silently goes back to matching against names.
         */
        $this->assertInstanceOf(
            ScoresFreeText::class,
            $this->app->make(RecommendationService::class),
        );
    }

    public function test_it_scores_students_from_what_the_model_says(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake([
            '*generativelanguage*' => Http::response($this->reply([
                ['id' => $student->id, 'compatibility' => 91, 'insight' => 'Has shipped two stock systems in Laravel.'],
            ])),
        ]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertSame(91, $scores[$student->id]['compatibility']);
        $this->assertSame('Has shipped two stock systems in Laravel.', $scores[$student->id]['reason']['insight']);
        $this->assertSame('gemini', $scores[$student->id]['reason']['source']);
    }

    public function test_the_key_travels_in_a_header_and_never_in_the_url(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 50, 'insight' => 'Plausible.'],
        ]))]);

        $this->app->make(RecommendationService::class)->scoresFor($project);

        Http::assertSent(function (Request $request): bool {
            $this->assertStringNotContainsString('test-key', $request->url());

            return $request->hasHeader('x-goog-api-key', 'test-key');
        });
    }

    public function test_no_name_or_email_is_ever_sent_to_the_model(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 50, 'insight' => 'Plausible.'],
        ]))]);

        $this->app->make(RecommendationService::class)->scoresFor($project);

        Http::assertSent(function (Request $request) use ($student): bool {
            $body = $request->body();

            $this->assertStringNotContainsString($student->name, $body);
            $this->assertStringNotContainsString($student->email, $body);

            return true;
        });
    }

    public function test_a_dead_service_falls_back_instead_of_failing(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response('upstream is down', 503)]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertEquals($this->computedScoresFor($project), $scores->all());
    }

    public function test_an_outage_is_discovered_once_not_by_every_reader(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response('upstream is down', 503)]);

        $service = $this->app->make(RecommendationService::class);

        /*
         * The fallback made a fault survivable but not cheap: it was forgotten
         * as soon as it was handled, so the next reader paid the whole timeout
         * to rediscover the same outage. With the API answering 503 that put
         * the timeout in front of the board on every single load.
         */
        $service->scoresFor($project);
        $service->scoresFor($project);
        $service->scoresFor($project);

        Http::assertSentCount(1);
    }

    public function test_the_model_is_asked_again_once_the_cooldown_lapses(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response('upstream is down', 503)]);

        $service = $this->app->make(RecommendationService::class);
        $service->scoresFor($project);

        $this->travel((int) config('gemini.cooldown_minutes') + 1)->minutes();

        $service->scoresFor($project);

        /* An outage is a pause, never a switch somebody has to come and flip. */
        Http::assertSentCount(2);
    }

    public function test_a_cooldown_of_zero_asks_every_time(): void
    {
        config()->set('gemini.cooldown_minutes', 0);

        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response('upstream is down', 503)]);

        $service = $this->app->make(RecommendationService::class);
        $service->scoresFor($project);
        $service->scoresFor($project);

        Http::assertSentCount(2);
    }

    public function test_a_reply_in_the_wrong_shape_falls_back(): void
    {
        [$project, $student] = $this->briefAndStudent();

        /* A model that answers in prose is a model that answered wrongly. */
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'They seem like a good fit!']]]]],
        ])]);

        $this->assertEquals(
            $this->computedScoresFor($project),
            $this->app->make(RecommendationService::class)->scoresFor($project)->all(),
        );
    }

    public function test_no_api_key_means_the_model_is_never_called(): void
    {
        config()->set('gemini.api_key', null);

        [$project] = $this->briefAndStudent();

        Http::fake();

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        Http::assertNothingSent();
        $this->assertEquals($this->computedScoresFor($project), $scores->all());
    }

    public function test_a_student_the_model_forgot_keeps_their_computed_score(): void
    {
        [$project, $student] = $this->briefAndStudent();

        $forgotten = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($forgotten)->create(['headline' => 'Laravel developer']);

        /* The reply names one of the two students. */
        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 88, 'insight' => 'Strong fit.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        /*
         * Dropping the other one would read as "this student does not exist"
         * rather than "the model had nothing to say about them".
         */
        $this->assertArrayHasKey($forgotten->id, $scores);
        $this->assertSame(88, $scores[$student->id]['compatibility']);
    }

    public function test_a_candidate_the_model_invented_is_discarded(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 70, 'insight' => 'Good fit.'],
            ['id' => 999999, 'compatibility' => 99, 'insight' => 'A student who does not exist.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertArrayNotHasKey(999999, $scores);
    }

    public function test_a_score_outside_the_scale_is_clamped(): void
    {
        [$project, $student] = $this->briefAndStudent();

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 420, 'insight' => 'Extremely keen.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertSame(100, $scores[$student->id]['compatibility']);
        $this->assertSame(1.0, $scores[$student->id]['score']);
    }

    /**
     * The computed answer for the same posting, for comparing a fallback to.
     *
     * @return array<int, mixed>
     */
    private function computedScoresFor(Project $project): array
    {
        return $this->app->make(ComputedRecommendationService::class)->scoresFor($project)->all();
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
     * A posting with enough words to be scoreable, and one student.
     *
     * Every field ScopeProfile reads is pinned; the factory fills them with
     * faker prose that infers skills of its own. See .ai/rules/matching.md.
     *
     * @return array{0: Project, 1: User}
     */
    private function briefAndStudent(): array
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'title' => 'Inventory System',
            'category' => 'Web application',
            'industry' => 'Retail',
            'description' => 'We need a way to track what is in the stockroom.',
            'objectives' => 'Record stock in and stock out.',
        ]);

        $student = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($student)->create(['headline' => 'Laravel and MySQL developer']);

        return [$project, $student->fresh()];
    }
}
