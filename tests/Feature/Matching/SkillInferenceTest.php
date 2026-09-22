<?php

namespace Tests\Feature\Matching;

use App\Models\Skill;
use App\Services\Matching\SkillInference;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * The vocabulary only helps if its slugs name skills that exist.
 *
 * An inferred slug nothing in the skills table carries is silently dropped
 * downstream, so a typo in the map reads as "nobody here can do this" rather
 * than as an error.
 */
class SkillInferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClientModuleTaxonomySeeder::class);
    }

    public function test_a_website_redesign_brief_infers_the_ui_ux_design_skill(): void
    {
        $uiUxDesign = Skill::where('name', 'UI/UX Design')->firstOrFail();

        $inferred = app(SkillInference::class)->fromText('We need a website redesign for our shop.');

        $this->assertContains(
            $uiUxDesign->slug,
            $inferred->all(),
            'A redesign is design work, so it should reach the UI/UX Design skill.',
        );
    }

    public function test_every_slug_in_the_vocabulary_names_a_seeded_skill(): void
    {
        $vocabulary = (new ReflectionClassConstant(SkillInference::class, 'DOMAIN_SKILLS'))->getValue();

        $unknown = collect($vocabulary)
            ->flatten()
            ->unique()
            ->diff(Skill::pluck('slug'))
            ->values();

        $this->assertEmpty(
            $unknown->all(),
            'These slugs match no skill: '.$unknown->implode(', '),
        );
    }
}
