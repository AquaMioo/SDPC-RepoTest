<?php

namespace Tests\Feature\Matching;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Get Client and Recruit are only as fast as the model lets them be.
 *
 * So the screens paint before the ranking (a deferred group), a search asks
 * the model once rather than twice, a model whose daily quota is spent is not
 * asked again until it resets, and a ranking is asked for at temperature zero
 * and keyed on everything it read, so the same inputs give the same order.
 */
class MatchingSpeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recommendations.driver', 'gemini');
        config()->set('gemini.api_key', 'test-key');
        config()->set('gemini.fallback_models', ['gemini-backup']);
    }

    public function test_the_board_paints_before_the_ranking_arrives(): void
    {
        [$student, $project] = $this->studentAndBrief();
        Http::fake(['*' => Http::response($this->reply([['id' => $project->id, 'compatibility' => 80, 'insight' => 'A fit.']]))]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('filters')
                ->has('capstone')
                ->missing('projects')
                ->missing('highlight')
                ->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload
                    ->where('projects.data.0.id', $project->id)
                    ->where('matchingEnabled', true)));
    }

    public function test_recruit_paints_before_the_ranking_arrives(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $this->studentWithSkills('Pia Reyes', ['mysql', 'payment-integration']);
        Http::fake(['*' => Http::response($this->reply([]))]);

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('filters')
                ->missing('students')
                ->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload->has('students.data', 1)));
    }

    public function test_a_recruit_search_asks_the_model_once(): void
    {
        $client = User::factory()->client()->approved()->verifiedBusiness()->create();
        $pia = $this->studentWithSkills('Pia Reyes', ['mysql', 'payment-integration']);
        $this->studentWithSkills('Ana Lim', ['mysql']);

        Http::fake(['*' => Http::response($this->reply([
            ['id' => $pia->user_id, 'compatibility' => 88, 'insight' => 'Has built a POS before.'],
        ]))]);

        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'search' => 'A point of sale system with payments for my store',
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('ranking', fn (AssertableInertia $reload) => $reload
                ->where('students.data.0.id', $pia->user_id)
                ->where('students.data.0.compatibility', 88)));

        /*
         * Ranked across everyone and labelled from the same answer: one call.
         * Only calls to the model count — with `npm run dev` up, Inertia also
         * posts to the Vite server's SSR endpoint, which is not this.
         */
        $this->assertCount(1, Http::recorded(
            fn ($request) => str_contains($request->url(), ':generateContent'),
        ));
    }

    public function test_a_model_whose_daily_quota_is_spent_rests_until_it_resets(): void
    {
        [$student, $project] = $this->studentAndBrief();
        $main = config('gemini.model');

        Http::fake([
            "*/models/{$main}:*" => Http::response($this->quotaSpent('GenerateRequestsPerDayPerProjectPerModel-FreeTier'), 429),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $project->id, 'compatibility' => 70, 'insight' => 'A fit.'],
            ])),
        ]);

        $recommendations = $this->app->make(RecommendationService::class);

        $recommendations->scoresForStudent($student);
        $this->assertSame(1, $this->sentTo($main));

        /* A second, uncached question: straight to the backup, the spent model left alone. */
        $recommendations->projectScoresForText('Inventory', 'A stock system.', $student);
        $this->assertSame(1, $this->sentTo($main));
        $this->assertSame(2, $this->sentTo('gemini-backup'));

        /* After midnight Pacific the quota is back, and so is the model. */
        $this->travelTo(now('America/Los_Angeles')->addDay()->startOfDay()->addMinute());
        $recommendations->projectScoresForText('Payroll', 'A payroll system.', $student);
        $this->assertSame(2, $this->sentTo($main));
    }

    public function test_a_per_minute_limit_rests_only_for_the_retry_delay(): void
    {
        [$student, $project] = $this->studentAndBrief();
        $main = config('gemini.model');

        Http::fake([
            "*/models/{$main}:*" => Http::response($this->quotaSpent('GenerateRequestsPerMinutePerProjectPerModel', retryDelay: '7s'), 429),
            '*/models/gemini-backup:*' => Http::response($this->reply([
                ['id' => $project->id, 'compatibility' => 60, 'insight' => 'A fit.'],
            ])),
        ]);

        $recommendations = $this->app->make(RecommendationService::class);

        $recommendations->projectScoresForText('Inventory', 'A stock system.', $student);
        $recommendations->projectScoresForText('Booking', 'A booking system.', $student);
        $this->assertSame(1, $this->sentTo($main));

        $this->travel(8)->seconds();
        $recommendations->projectScoresForText('Payroll', 'A payroll system.', $student);
        $this->assertSame(2, $this->sentTo($main));
    }

    public function test_when_every_model_is_spent_nobody_is_asked(): void
    {
        [$student, $project] = $this->studentAndBrief();

        Http::fake(['*' => Http::response($this->quotaSpent('GenerateRequestsPerDayPerProjectPerModel-FreeTier'), 429)]);

        $recommendations = $this->app->make(RecommendationService::class);
        $recommendations->scoresForStudent($student);
        $sent = count(Http::recorded());

        $this->travel(10)->minutes();
        $scores = $recommendations->projectScoresForText('Payroll', 'A payroll system.', $student);

        $this->assertSame($sent, count(Http::recorded()));
        /* The board still ranks — by the computed scorer. */
        $this->assertNotSame('gemini', $scores[$project->id]['reason']['source'] ?? null);
    }

    public function test_a_ranking_is_asked_for_at_temperature_zero(): void
    {
        [$student] = $this->studentAndBrief();
        Http::fake(['*' => Http::response($this->reply([]))]);

        $this->app->make(RecommendationService::class)->scoresForStudent($student);

        Http::assertSent(fn (Request $request): bool => ($request->data()['generationConfig']['temperature'] ?? null) === 0);
    }

    public function test_a_posting_published_since_is_ranked_by_the_model_too(): void
    {
        [$student, $project] = $this->studentAndBrief();
        Http::fake(['*' => Http::response($this->reply([
            ['id' => $project->id, 'compatibility' => 75, 'insight' => 'A fit.'],
        ]))]);

        $recommendations = $this->app->make(RecommendationService::class);

        $recommendations->scoresForStudent($student);
        $recommendations->scoresForStudent($student);
        Http::assertSentCount(1);

        $this->openBrief('Booking board for a salon');

        $recommendations->scoresForStudent($student);
        Http::assertSentCount(2);
    }

    /**
     * How many requests went to one model.
     */
    private function sentTo(string $model): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), "/models/{$model}:"))
            ->count();
    }

    /**
     * A 429 body the way Google writes it.
     *
     * @return array<string, mixed>
     */
    private function quotaSpent(string $quotaId, ?string $retryDelay = null): array
    {
        return ['error' => [
            'code' => 429,
            'status' => 'RESOURCE_EXHAUSTED',
            'details' => array_values(array_filter([
                ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => $quotaId]]],
                $retryDelay === null ? null : ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $retryDelay],
            ])),
        ]];
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

        return [$student->fresh(), $this->openBrief('Counter and stock system for a hardware store')];
    }

    /**
     * An open posting, every field the scope reads pinned (.ai/rules/matching.md).
     */
    private function openBrief(string $title): Project
    {
        return Project::factory()->create([
            'title' => $title,
            'category' => 'Web application',
            'industry' => 'Retail',
            'description' => 'We still run the counter on a paper ledger and count stock by walking the aisles.',
            'objectives' => 'Record stock in and stock out.',
            'status' => ProjectStatus::Open,
            'applications_open' => true,
        ]);
    }

    /**
     * A student on Recruit with the given skill slugs.
     *
     * @param  list<string>  $slugs
     */
    private function studentWithSkills(string $name, array $slugs): StudentProfile
    {
        $user = User::factory()->student()->approved()->create(['name' => $name]);
        $profile = StudentProfile::factory()->for($user)->create();

        foreach ($slugs as $slug) {
            $profile->skills()->attach(Skill::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace('-', ' ')->title()->toString()],
            ));
        }

        return $profile;
    }
}
