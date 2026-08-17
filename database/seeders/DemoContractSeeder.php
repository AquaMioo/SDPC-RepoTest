<?php

namespace Database\Seeders;

use App\Actions\Agreements\SignAgreement;
use App\Actions\Client\RespondToApplication;
use App\Enums\AgreementParty;
use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\Application;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One build, walked from posting to signed contract.
 *
 * Not part of DatabaseSeeder — run it by hand when you want the Agreement,
 * Contract and Project process screens to have something on them:
 *
 *     php artisan db:seed --class=DemoContractSeeder
 *
 * It goes through the real actions rather than writing rows directly, so the
 * result is a contract the platform itself produced: acceptance drafts it,
 * the second signature activates it and moves the posting into progress.
 */
class DemoContractSeeder extends Seeder
{
    /**
     * Build the demo contract.
     */
    public function run(): void
    {
        $client = User::firstWhere('email', 'client.one@sdpc.test');
        $student = User::firstWhere('email', 'student.one@sdpc.test');

        if ($client === null || $student === null) {
            $this->command?->error('Run the base seeder first — this needs the tester accounts.');

            return;
        }

        $project = $this->posting($client);
        $application = $this->application($project, $student);

        app(RespondToApplication::class)
            ->handle($application, ApplicationStatus::Accepted, $client);

        $agreement = $application->refresh()->agreement;

        $this->priceTheTerms($agreement);
        $this->signBothSides($agreement, $client, $student);
        $this->startTheWork($agreement->refresh());

        $this->command?->info('Demo contract '.$agreement->reference.' is active.');
    }

    /**
     * Put a posting on the board.
     *
     * Written straight to Open rather than through the review queue, which is
     * the one thing here that skips a real door: AdminPostingController is
     * normally the only place a posting becomes Open.
     */
    private function posting(User $client): Project
    {
        $project = Project::updateOrCreate(
            ['slug' => 'northwind-counter-and-stock-system'],
            [
                'team_id' => $client->current_team_id,
                'created_by' => $client->id,
                'title' => 'Counter and stock system for a hardware store',
                'description' => 'We still run the counter on a paper ledger and count stock by walking the aisles. We need a system the counter staff can use on a busy Saturday without training, and a stock view the owner can read from home.',
                'objectives' => "Point-of-sale for the counter\nStock levels per branch with reorder alerts\nSupplier and delivery records\nDaily sales summary the owner can read on a phone",
                'category' => 'Web system',
                'industry' => 'Retail and hardware',
                'status' => ProjectStatus::Open,
                'applications_open' => true,
                'published_at' => now()->subWeeks(2),
            ],
        );

        $project->skills()->sync(Skill::idsForNames(['Laravel', 'MySQL', 'React']));

        return $project;
    }

    /**
     * Have the student apply.
     */
    private function application(Project $project, User $student): Application
    {
        return Application::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $student->id],
            [
                'status' => ApplicationStatus::Pending,
                'source' => ApplicationSource::Applied,
                'cover_letter' => 'I built a point-of-sale and stock system for a grocery in Towerville last term, including the reorder alerts. I can show you the counter screen running before we agree on anything.',
                /* There is no applied_at column — created_at is the date applied. */
                'created_at' => now()->subWeek(),
            ],
        );
    }

    /**
     * Fill in what the client would fill in before anybody signs.
     *
     * SignAgreement refuses an agreement with an unpriced or undated
     * milestone, so this is not optional decoration.
     */
    private function priceTheTerms(Agreement $agreement): void
    {
        $agreement->update([
            'scope_summary' => 'A point-of-sale and stock system for one hardware store, with reorder alerts and a daily summary.',
            'starts_on' => now()->subWeek()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
        ]);

        $schedule = [
            ['Design', 8000, -7, 14, 'Counter screens and stock model agreed with the owner.'],
            ['Build', 14000, 15, 45, 'Point-of-sale, stock, suppliers and the daily summary.'],
            ['Turnover', 6000, 46, 60, 'Deployment, staff walkthrough and written handover.'],
        ];

        foreach ($agreement->milestones as $index => $milestone) {
            [$title, $amount, $from, $to, $description] = $schedule[$index] ?? $schedule[0];

            $milestone->update([
                'title' => $title,
                'description' => $description,
                'amount' => $amount,
                'starts_on' => now()->addDays($from)->toDateString(),
                'ends_on' => now()->addDays($to)->toDateString(),
            ]);
        }

        $agreement->refresh()->syncTotalAmount();
    }

    /**
     * Both signatures, which is what starts the project.
     */
    private function signBothSides(Agreement $agreement, User $client, User $student): void
    {
        $acknowledgements = array_keys((array) config('agreements.acknowledgements'));

        $sign = app(SignAgreement::class);

        $sign->handle($agreement, $client, AgreementParty::Client, $client->name, $acknowledgements);
        $sign->handle($agreement->refresh(), $student, AgreementParty::Student, $student->name, $acknowledgements);
    }

    /**
     * Move the build far enough along that the ring has something to report.
     */
    private function startTheWork(Agreement $agreement): void
    {
        $milestones = $agreement->milestones;

        $milestones[0]?->update([
            'status' => MilestoneStatus::Approved,
            'approved_at' => now()->subDays(3),
        ]);

        $milestones[1]?->update(['status' => MilestoneStatus::InProgress]);
    }
}
