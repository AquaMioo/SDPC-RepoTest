<?php

namespace Tests\Feature\Agreements\Concerns;

use App\Enums\AgreementStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;

/**
 * A client and a student collaborating on one project.
 *
 * Collaboration means a signed agreement — status Active — which is the only
 * state Project Management opens in. Three phases, Design / Build / Turnover,
 * the way DraftAgreement seeds a real one, each with agreed dates.
 */
trait StartsCollaboration
{
    /**
     * Build a business, a student and the agreement between them.
     *
     * @return array{client: User, student: User, agreement: Agreement}
     */
    protected function collaboration(AgreementStatus $status = AgreementStatus::Active): array
    {
        $client = User::factory()->client()->verifiedBusiness()->create();
        $student = User::factory()->student()->approved()->create();

        $project = Project::factory()->create([
            'team_id' => $client->current_team_id,
            'created_by' => $client->id,
            'status' => ProjectStatus::InProgress,
        ]);

        $application = Application::factory()->create([
            'project_id' => $project->id,
            'user_id' => $student->id,
            'status' => ApplicationStatus::Accepted,
        ]);

        $agreement = Agreement::factory()->create([
            'project_id' => $project->id,
            'application_id' => $application->id,
            'team_id' => $client->current_team_id,
            'student_id' => $student->id,
            'status' => $status,
            'activated_at' => $status === AgreementStatus::Active ? now() : null,
            'starts_on' => '2026-02-02',
            'ends_on' => '2026-04-12',
        ]);

        foreach ([
            ['Design', '2026-02-02', '2026-02-22'],
            ['Build', '2026-02-23', '2026-03-29'],
            ['Turnover', '2026-03-30', '2026-04-12'],
        ] as $index => [$title, $startsOn, $endsOn]) {
            AgreementMilestone::factory()->create([
                'agreement_id' => $agreement->id,
                'position' => $index + 1,
                'title' => $title,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
        }

        return [
            'client' => $client->refresh(),
            'student' => $student->refresh(),
            'agreement' => $agreement->load('milestones'),
        ];
    }

    /**
     * The route parameters for a task action, from one party's team.
     *
     * Each side reaches the agreement through its own team — the client
     * through the business, the student through their personal team — so the
     * slug is pinned explicitly rather than left to URL defaults.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function asParty(User $user, Agreement $agreement, array $extra = []): array
    {
        return [
            'current_team' => $user->currentTeam,
            'agreement' => $agreement,
            ...$extra,
        ];
    }
}
