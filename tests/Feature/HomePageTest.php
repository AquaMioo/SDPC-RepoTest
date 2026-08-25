<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The landing page is the one screen the whole town can see, so nothing on it
 * is allowed to be decorative. Every figure is counted, and the wall of client
 * quotes is empty until a client writes one.
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_out_visitor_can_reach_it(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_a_brand_new_platform_reports_zeroes_rather_than_invented_figures(): void
    {
        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('welcome')
                ->where('stats.students', 0)
                ->where('stats.projectsCompleted', 0)
                ->where('stats.clients', 0)
                ->where('testimonials', [])
        );
    }

    public function test_it_counts_registered_students(): void
    {
        User::factory()->student()->count(3)->create();
        // A client must not be counted among the students.
        User::factory()->client()->create();

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.students', 3)
        );
    }

    public function test_a_student_still_awaiting_approval_is_still_a_registered_student(): void
    {
        User::factory()->student()->create(['status' => UserStatus::Pending]);

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.students', 1)
        );
    }

    public function test_a_deactivated_student_stops_being_counted(): void
    {
        User::factory()->student()->count(2)->create();
        User::factory()->student()->create(['status' => UserStatus::Deactivated]);

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.students', 2)
        );
    }

    public function test_it_counts_businesses_rather_than_client_accounts(): void
    {
        $team = $this->business();
        // A second member of the same shop is not a second local client.
        $team->members()->attach(User::factory()->client()->create(), ['role' => 'member']);

        $this->business();

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.clients', 2)
        );
    }

    public function test_a_closed_business_stops_being_counted(): void
    {
        $this->business('Still Trading');
        $closed = $this->business('Shut Down');

        // Team soft deletes, so its profile row outlives it.
        $closed->delete();

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.clients', 1)
        );
    }

    public function test_only_finished_projects_count_as_completed(): void
    {
        $team = $this->business();

        Project::factory()->for($team)->create(['status' => ProjectStatus::Completed]);
        Project::factory()->for($team)->create(['status' => ProjectStatus::InProgress]);
        Project::factory()->for($team)->create(['status' => ProjectStatus::Open]);

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.projectsCompleted', 1)
        );
    }

    /**
     * The hero's stars are averaged, never written down.
     *
     * They used to be a hard-coded "4.7 average from 205 surveyed students"
     * that no data supported. Nothing writes rating_average yet, so on a real
     * database the row is absent rather than confident.
     */
    public function test_the_hero_shows_no_rating_until_a_student_has_one(): void
    {
        StudentProfile::factory()->count(3)->create(['rating_average' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.studentRating', null)
                ->etc());
    }

    public function test_the_hero_averages_the_students_who_are_rated(): void
    {
        StudentProfile::factory()->create(['rating_average' => 4.0]);
        StudentProfile::factory()->create(['rating_average' => 5.0]);
        // Unrated, and therefore not averaged — a default 0 would drag the
        // figure down and report an absent score as a poor one.
        StudentProfile::factory()->create(['rating_average' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.studentRating.average', 4.5)
                ->where('stats.studentRating.rated', 2)
                ->etc());
    }

    public function test_client_satisfaction_reads_as_no_data_rather_than_zero(): void
    {
        // Nothing measures it yet. Zero would be a claim; null is the truth.
        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('stats.clientSatisfaction', null)
        );
    }

    public function test_a_written_testimonial_appears_with_its_attribution(): void
    {
        $team = $this->business('Northwind Trading');
        $author = User::factory()->client()->create(['name' => 'Lolene Javier']);

        Testimonial::factory()->for($team)->create([
            'user_id' => $author->id,
            'body' => 'A student team scoped our point-of-sale system in a week.',
            'author_title' => 'Owner',
        ]);

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('testimonials', 1)
                ->where('testimonials.0.quote', 'A student team scoped our point-of-sale system in a week.')
                ->where('testimonials.0.name', 'Lolene Javier')
                ->where('testimonials.0.role', 'Owner, Northwind Trading')
        );
    }

    public function test_a_testimonial_falls_back_to_the_business_when_its_author_is_gone(): void
    {
        $team = $this->business('Bayview Logistics');
        $author = User::factory()->client()->create();

        $testimonial = Testimonial::factory()->for($team)->create(['user_id' => $author->id]);

        $author->delete();

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('testimonials', 1)
                ->where('testimonials.0.name', 'Bayview Logistics')
        );

        $this->assertNull($testimonial->fresh()->user_id);
    }

    public function test_a_testimonial_without_a_business_profile_is_not_shown(): void
    {
        // Nothing to attribute the quote to, so it stays off the page.
        Testimonial::factory()->for(Team::factory()->create())->create();

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('testimonials', [])
        );
    }

    public function test_the_most_recently_written_testimonial_leads(): void
    {
        $older = $this->business('Older Shop');
        $newer = $this->business('Newer Shop');

        Testimonial::factory()->for($older)->create(['updated_at' => now()->subWeek()]);
        Testimonial::factory()->for($newer)->create(['updated_at' => now()]);

        $this->get(route('home'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('testimonials.0.role', fn (?string $role) => str_contains((string) $role, 'Newer Shop'))
        );
    }

    /**
     * Create a verified business with a profile to attribute quotes to.
     */
    private function business(string $name = 'Northwind Trading'): Team
    {
        $team = Team::factory()->create(['name' => $name]);

        ClientProfile::create([
            'team_id' => $team->id,
            'business_name' => $name,
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);

        return $team;
    }
}
