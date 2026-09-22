<?php

namespace Tests\Feature\Matching;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Recommendation\ComputedRecommendationService;
use App\Services\Recommendation\GeminiRecommendationService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\ScoresFreeText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

        /* One model, unless a test is about handing over to the next. */
        config()->set('gemini.fallback_models', []);
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

    public function test_a_busy_model_hands_the_question_to_the_next_one(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project, $student] = $this->briefAndStudent();

        /*
         * What the site saw on 2026-09-19: the pinned model answering 503
         * "high demand" between successful calls, and every one of those 503s
         * putting the whole site on keyword matching for the cooldown.
         */
        Http::fake([
            '*/models/'.config('gemini.model').':*' => Http::response('This model is currently experiencing high demand.', 503),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $student->id, 'compatibility' => 84, 'insight' => 'Has built stock systems in Laravel.'],
            ])),
        ]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertSame(84, $scores[$student->id]['compatibility']);
        $this->assertSame('gemini', $scores[$student->id]['reason']['source']);
        Http::assertSentCount(2);

        /* Answered is answered: nobody after this reader is cooled down. */
        $this->assertFalse(Cache::has('gemini.cooldown'));
    }

    public function test_a_rate_limit_counts_as_busy(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project, $student] = $this->briefAndStudent();

        Http::fake([
            '*/models/'.config('gemini.model').':*' => Http::response('Resource has been exhausted.', 429),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $student->id, 'compatibility' => 77, 'insight' => 'Plausible.'],
            ])),
        ]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertSame(77, $scores[$student->id]['compatibility']);
    }

    public function test_a_model_that_stalls_hands_the_question_to_the_next_one(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project, $student] = $this->briefAndStudent();

        /*
         * What both sites saw on 2026-09-22: the pinned model holding the
         * request until the timeout, which used to end the question there.
         */
        Http::fake([
            '*/models/'.config('gemini.model').':*' => Http::failedConnection('cURL error 28: Operation timed out after 6000 milliseconds with 0 bytes received'),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $student->id, 'compatibility' => 81, 'insight' => 'Has built stock systems in Laravel.'],
            ])),
        ]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertSame(81, $scores[$student->id]['compatibility']);
        $this->assertSame('gemini', $scores[$student->id]['reason']['source']);
        Http::assertSentCount(2);
        $this->assertFalse(Cache::has('gemini.cooldown'));
    }

    public function test_each_model_gets_a_slice_of_the_budget_not_all_of_it(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);
        config()->set('gemini.timeout', 12);
        config()->set('gemini.attempt_timeout', 6);

        [$project] = $this->briefAndStudent();

        $timeouts = [];

        Http::fake(function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response('This model is currently experiencing high demand.', 503);
        });

        $this->app->make(RecommendationService::class)->scoresFor($project);

        $this->assertCount(2, $timeouts);

        foreach ($timeouts as $timeout) {
            $this->assertLessThanOrEqual(6, $timeout);
        }
    }

    public function test_every_model_stalling_falls_back_and_cools_down(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project] = $this->briefAndStudent();

        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $service = $this->app->make(RecommendationService::class);

        $this->assertEquals($this->computedScoresFor($project), $service->scoresFor($project)->all());
        Http::assertSentCount(2);
        $this->assertTrue(Cache::has('gemini.cooldown'));
    }

    public function test_a_busy_model_rests_while_a_healthy_one_keeps_answering(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project, $student] = $this->briefAndStudent();

        /*
         * Measured from sdpc.tech on 2026-09-22: overload is per model, not
         * across the board — the pinned model answered every probe in 1-2
         * seconds while the lite models 503'd. The busy one is put aside so
         * the next question does not spend the budget rediscovering it, and
         * nothing is cooled down, because a model is still answering.
         */
        Http::fake([
            '*/models/'.config('gemini.model').':*' => Http::response('This model is currently experiencing high demand.', 503),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $student->id, 'compatibility' => 77, 'insight' => 'Has shipped stockroom tooling.'],
            ])),
        ]);

        $service = $this->app->make(RecommendationService::class);

        $this->assertSame(77, $service->scoresFor($project)[$student->id]['compatibility']);
        Http::assertSentCount(2);
        $this->assertFalse(Cache::has('gemini.cooldown'));

        /* The next question skips the resting model and goes straight to the one answering. */
        Cache::forget('gemini.project.'.$project->id.'.'.$project->updated_at?->timestamp);
        $service->scoresFor($project->fresh());

        Http::assertSentCount(3);
    }

    public function test_a_refusal_is_not_put_to_the_next_model(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project] = $this->briefAndStudent();

        /* A bad request is bad for every model; asking again only costs time. */
        Http::fake(['*' => Http::response('Invalid argument.', 400)]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        Http::assertSentCount(1);
        $this->assertEquals($this->computedScoresFor($project), $scores->all());
    }

    public function test_every_model_busy_falls_back_and_cools_down(): void
    {
        config()->set('gemini.fallback_models', ['gemini-backup']);

        [$project] = $this->briefAndStudent();

        Http::fake(['*' => Http::response('This model is currently experiencing high demand.', 503)]);

        $service = $this->app->make(RecommendationService::class);

        $this->assertEquals($this->computedScoresFor($project), $service->scoresFor($project)->all());
        Http::assertSentCount(2);

        $service->scoresFor($project);

        /* Discovered once, like any other outage. */
        Http::assertSentCount(2);
        $this->assertTrue(Cache::has('gemini.cooldown'));
    }

    public function test_a_student_past_the_shortlist_keeps_their_computed_score(): void
    {
        config()->set('gemini.max_candidates', 1);

        [$project, $student] = $this->briefAndStudent();

        $unseen = User::factory()->student()->approved()->create();
        StudentProfile::factory()->for($unseen)->create(['headline' => 'Laravel developer']);

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $student->id, 'compatibility' => 88, 'insight' => 'Strong fit.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresFor($project);

        /*
         * max_candidates bounds the prompt. It used to bound the results too:
         * the thirty-first student silently disappeared from Recruit.
         */
        $this->assertSame(88, $scores[$student->id]['compatibility']);
        $this->assertEquals($this->computedScoresFor($project)[$unseen->id], $scores[$unseen->id]);
    }

    public function test_a_brief_past_the_shortlist_keeps_its_computed_score(): void
    {
        config()->set('gemini.max_candidates', 1);

        [$project, $student] = $this->briefAndStudent();

        $unseen = Project::factory()->create([
            'team_id' => $project->team_id,
            'status' => ProjectStatus::Open,
            'title' => 'Point of Sale',
            'category' => 'Web application',
            'industry' => 'Retail',
            'description' => 'A till for our shop that records every sale.',
            'objectives' => 'Ring up sales and print receipts.',
        ]);

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $project->id, 'compatibility' => 81, 'insight' => 'A close fit.'],
        ]))]);

        $scores = $this->app->make(RecommendationService::class)->scoresForStudent($student);
        $computed = $this->app->make(ComputedRecommendationService::class)->scoresForStudent($student);

        $this->assertSame(81, $scores[$project->id]['compatibility']);
        $this->assertEquals($computed[$unseen->id], $scores[$unseen->id]);
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
