<?php

namespace Tests\Feature\Matching;

use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Matching\MatchingEngine;
use App\Services\Matching\ScopeProfile;
use App\Services\Matching\SkillInference;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The scorer behind both directions of matching.
 *
 * The case that matters most is the one a client actually types: a description
 * of a problem, not a stack. "POS for my store" has to reach students who list
 * payment integration and MySQL, none of whom list "POS".
 */
class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClientModuleTaxonomySeeder::class);
    }

    public function test_it_reads_skills_out_of_a_plain_description(): void
    {
        $inferred = app(SkillInference::class)->fromText('POS to my website system');

        // Nobody lists "POS" as a skill, so the words have to be translated.
        $this->assertContains('payment-integration', $inferred->all());
        $this->assertContains('mysql', $inferred->all());
        $this->assertContains('react', $inferred->all(), 'The "website" half should count too.');
    }

    public function test_it_also_picks_up_skills_named_outright(): void
    {
        $inferred = app(SkillInference::class)->fromText('We need Laravel and Flutter for this.');

        $this->assertContains('laravel', $inferred->all());
        $this->assertContains('flutter', $inferred->all());
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function wordFormProvider(): array
    {
        return [
            'plural' => ['we take online orders', 'ordering'],
            'agent noun' => ['a delivery tracker for riders', 'tracking'],
            'gerund plural' => ['manage bookings', 'booking'],
            'plural noun' => ['a system for recording sales', 'sales'],
            'singular' => ['one appointment per slot', 'appointment'],
        ];
    }

    /**
     * A client types whichever form comes to mind. The vocabulary stores one.
     */
    #[DataProvider('wordFormProvider')]
    public function test_word_forms_reach_the_same_entry(string $text, string $expected): void
    {
        $phrases = app(SkillInference::class)->phrasesIn($text);

        $this->assertContains(
            $expected,
            $phrases->all(),
            "\"{$text}\" should have reached the \"{$expected}\" entry.",
        );
    }

    /**
     * @return list<array{0: string}>
     */
    public static function localBusinessProvider(): array
    {
        return [
            ['sari-sari store inventory'],
            ['utang at lista tracker'],
            ['bayad center system'],
            ['piso wifi vendo monitoring'],
            ['boarding house rental management'],
            ['barangay clearance and records'],
            ['water refilling station delivery'],
            ['carinderia ordering system'],
            ['poultry farm monitoring'],
            ['tricycle TODA membership'],
            ['botica pharmacy inventory'],
            ['resibo at official receipt'],
        ];
    }

    /**
     * The requests a San Jose Del Monte business would actually make.
     *
     * A phrase that infers nothing ranks nobody, so the client sees an empty
     * page and concludes the platform has no students. These are the words
     * the vocabulary exists to understand.
     */
    #[DataProvider('localBusinessProvider')]
    public function test_a_local_business_request_is_understood(string $request): void
    {
        $skills = app(SkillInference::class)->fromText($request);

        $this->assertNotEmpty(
            $skills->all(),
            "\"{$request}\" inferred no skills, so nobody would be ranked for it.",
        );
    }

    public function test_stemming_does_not_collapse_unrelated_words(): void
    {
        // Over-stemming is tolerable while it stays symmetric; reaching a
        // different idea entirely is not.
        $phrases = app(SkillInference::class)->phrasesIn('our ordeal with the old software');

        $this->assertNotContains('ordering', $phrases->all());
    }

    public function test_a_word_inside_another_word_is_not_a_match(): void
    {
        $inferred = app(SkillInference::class)->fromText('Our company is happy with the current setup.');

        // "map" inside "company" and "app" inside "happy" would quietly wreck
        // a score if the matching were naive.
        $this->assertNotContains('api-integration', $inferred->all());
        $this->assertNotContains('flutter', $inferred->all());
    }

    public function test_the_student_who_can_build_it_scores_higher(): void
    {
        $scope = ScopeProfile::fromSearch('POS to my website system', app(SkillInference::class));

        $capable = $this->student(['payment-integration', 'mysql', 'php', 'laravel', 'react']);
        $unrelated = $this->student(['swift', 'spring-boot']);

        $engine = app(MatchingEngine::class);

        $capableScore = $engine->score($scope, $capable)->compatibility;
        $unrelatedScore = $engine->score($scope, $unrelated)->compatibility;

        $this->assertGreaterThan(
            $unrelatedScore,
            $capableScore,
            'A student with the skills a POS needs must outrank one without them.',
        );
    }

    public function test_the_explanation_names_the_skills_that_matched(): void
    {
        $scope = ScopeProfile::fromSearch('inventory system with forecasting', app(SkillInference::class));

        $result = app(MatchingEngine::class)->score($scope, $this->student(['mysql', 'forecasting']));

        $this->assertContains('Mysql', $result->matchedSkills);
        $this->assertContains('Forecasting', $result->matchedSkills);
        $this->assertNotSame('', $result->insight);
        $this->assertNotSame('', $result->recommendation);
    }

    public function test_every_factor_is_a_percentage(): void
    {
        $scope = ScopeProfile::fromSearch('mobile ordering app', app(SkillInference::class));

        $result = app(MatchingEngine::class)->score($scope, $this->student(['flutter', 'dart']));

        $this->assertNotEmpty($result->factors);

        foreach ($result->factors as $factor) {
            $this->assertArrayHasKey('label', $factor);
            $this->assertGreaterThanOrEqual(0, $factor['value']);
            $this->assertLessThanOrEqual(100, $factor['value']);
        }

        $this->assertGreaterThanOrEqual(0, $result->compatibility);
        $this->assertLessThanOrEqual(100, $result->compatibility);
    }

    public function test_an_unavailable_student_scores_below_an_identical_available_one(): void
    {
        $scope = ScopeProfile::fromSearch('inventory system', app(SkillInference::class));

        $available = $this->student(['mysql', 'forecasting']);
        $busy = $this->student(['mysql', 'forecasting'], ['is_available' => false]);

        $engine = app(MatchingEngine::class);

        $this->assertGreaterThan(
            $engine->score($scope, $busy)->compatibility,
            $engine->score($scope, $available)->compatibility,
        );
    }

    public function test_a_newcomer_is_not_ranked_at_the_floor(): void
    {
        $scope = ScopeProfile::fromSearch('inventory system', app(SkillInference::class));

        $newcomer = $this->student(['mysql', 'forecasting', 'data-analytics'], [
            'rating_average' => 0,
            'completed_projects_count' => 0,
        ]);

        // A platform for students that buried first-timers would never place
        // one, so no history sits mid-scale rather than at zero.
        $this->assertGreaterThanOrEqual(
            50,
            app(MatchingEngine::class)->score($scope, $newcomer)->compatibility,
        );
    }

    public function test_a_scope_with_nothing_to_go_on_is_not_scored(): void
    {
        $scope = ScopeProfile::fromSearch('hello there', app(SkillInference::class));

        // Better to say nothing than to print a confident number built on air.
        $this->assertFalse($scope->isMeaningful());
    }

    /**
     * A student profile holding the given skills.
     *
     * @param  list<string>  $skillSlugs
     * @param  array<string, mixed>  $attributes
     */
    private function student(array $skillSlugs, array $attributes = []): StudentProfile
    {
        $profile = StudentProfile::factory()
            ->for(User::factory()->student())
            ->create([
                'is_available' => true,
                'rating_average' => 4.0,
                'completed_projects_count' => 2,
                ...$attributes,
            ]);

        $profile->skills()->sync(Skill::whereIn('slug', $skillSlugs)->pluck('id'));

        return $profile->load('skills');
    }
}
