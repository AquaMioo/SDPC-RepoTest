<?php

namespace Tests\Feature\Client;

use App\Enums\CredentialStatus;
use App\Models\Skill;
use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\ClientModuleTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What the recruit row is allowed to say about a student.
 *
 * The screen reads as a claim about somebody, so every line on it has to come
 * from a column: the badge from a credential an administrator accepted, the
 * proof lines from portfolio items the student wrote, the rating from finished
 * work. A student nobody has finished a project with says so rather than
 * showing a 0.0 that reads as a bad score.
 */
class RecruitDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClientModuleTaxonomySeeder::class);
    }

    public function test_the_row_carries_the_verified_badge_only_for_an_accepted_credential(): void
    {
        $client = $this->client();

        $unproven = $this->student('Ana Lim');
        $this->student('Pia Reyes', verified: true);

        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'sort' => 'name',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('client/recruit')
                ->where('students.data.0.name', $unproven->user->name)
                ->where('students.data.0.isVerified', false)
                ->where('students.data.1.name', 'Pia Reyes')
                ->where('students.data.1.isVerified', true),
            );
    }

    public function test_the_row_shows_documented_work_and_never_more_than_two_lines(): void
    {
        $client = $this->client();
        $profile = $this->student('Pia Reyes');

        foreach (['Inventory system', 'Booking board', 'Payroll ledger'] as $position => $title) {
            StudentPortfolioItem::factory()->for($profile)->create([
                'title' => $title,
                'position' => $position,
            ]);
        }

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data.0.highlights', 2)
                ->where('students.data.0.highlights.0', 'Inventory system')
                ->where('students.data.0.highlights.1', 'Booking board'),
            );
    }

    public function test_a_student_with_no_portfolio_gets_no_invented_proof(): void
    {
        $client = $this->client();
        $this->student('Ana Lim');

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('students.data.0.highlights', [])
                ->where('students.data.0.location', null),
            );
    }

    /**
     * The rail is the reasoning behind the ranking, so with nothing ranked
     * there is nothing to show — an empty set of bars would read as a student
     * who scored zero rather than as a page nobody asked a question of.
     */
    public function test_the_matching_rail_is_absent_until_something_is_scored(): void
    {
        $client = $this->client();
        $this->student('Ana Lim');

        $this->actingAs($client)
            ->get(route('recruit.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('matchingEnabled', false)
                ->where('highlight', null),
            );
    }

    public function test_the_matching_rail_describes_the_strongest_fit(): void
    {
        $client = $this->client();

        $this->student('Pia Reyes', skills: ['laravel', 'mysql', 'php', 'payment-integration']);
        $this->student('Ana Lim', skills: ['flutter']);

        $this->actingAs($client)
            ->get(route('recruit.index', [
                'current_team' => $client->currentTeam,
                'search' => 'A point of sale system with payments for my store',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('matchingEnabled', true)
                ->where('highlight.name', 'Pia Reyes')
                // Every bar on the rail is a factor the engine actually
                // computed, not a label the design asked for.
                ->has('highlight.factors')
                ->has('highlight.matchedSkills'),
            );
    }

    /**
     * A client who may look at the directory.
     */
    private function client(): User
    {
        return User::factory()->client()->approved()->verifiedBusiness()->create();
    }

    /**
     * A findable student, optionally with a credential an administrator took.
     *
     * @param  list<string>  $skills
     */
    private function student(string $name, bool $verified = false, array $skills = ['mysql']): StudentProfile
    {
        $user = User::factory()->student()->approved()->create(['name' => $name]);

        $profile = StudentProfile::factory()->for($user)->create([
            'is_available' => true,
            'barangay' => null,
            'rating_average' => 0,
            'completed_projects_count' => 0,
        ]);

        $profile->skills()->sync(Skill::whereIn('slug', $skills)->pluck('id'));

        if ($verified) {
            $user->studentCredentials()->create([
                'school' => 'City College of Technology',
                'disk' => 'local',
                'path' => 'credentials/'.$user->id.'.pdf',
                'original_name' => 'registration.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'checksum' => hash('sha256', (string) $user->id),
            ])->forceFill(['status' => CredentialStatus::Verified])->save();
        }

        return $profile->fresh();
    }
}
