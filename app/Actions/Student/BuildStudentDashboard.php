<?php

namespace App\Actions\Student;

use App\Actions\Agreements\SummariseProgress;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\SiteContentKey;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\User;

/**
 * Gathers everything the student dashboard shows.
 *
 * A posting carries no dates and no money — it is a brief. The schedule lives
 * on the signed agreement instead, so the ring reports the tasks the client
 * verified and the calendar marks the phase dates the student is working to.
 * Both fall back to nothing rather than to a guess: a student with no signed
 * agreement sees a dash, which is different from a zero.
 */
class BuildStudentDashboard
{
    /**
     * Build the dashboard payload for a student.
     *
     * @return array<string, mixed>
     */
    public function handle(User $student): array
    {
        /*
         * A teammate works on their team leader's build, so their dashboard is
         * that build: the leader holds the accepted application and signed
         * the agreement. See User::projectHolder().
         */
        $holder = $student->projectHolder() ?? $student;

        $project = $this->activeProject($holder);
        $agreement = $this->activeAgreement($holder, $project);

        return [
            'project' => $this->project($project, $agreement),
            'calendar' => ['marks' => $this->milestoneMarks($agreement)],
            'announcement' => $this->announcement(),
        ];
    }

    /**
     * Get the agreement governing the student's current build.
     *
     * Only an active one counts. A draft is a proposal, and reporting progress
     * against terms nobody has signed would put a number on work that has not
     * been agreed to yet.
     */
    protected function activeAgreement(User $student, ?Project $project): ?Agreement
    {
        if ($project === null) {
            return null;
        }

        return Agreement::query()
            ->where('student_id', $student->id)
            ->where('project_id', $project->id)
            ->active()
            ->with('milestones.tasks.pendingDeadlineRequest')
            ->latest('version')
            ->first();
    }

    /**
     * Get the project the student is currently building.
     *
     * Accepted applications are the membership record, so "my project" is the
     * most recently published one the student was accepted onto.
     */
    protected function activeProject(User $student): ?Project
    {
        return Project::query()
            ->whereHas('applications', fn ($query) => $query
                ->where('user_id', $student->id)
                ->where('status', ApplicationStatus::Accepted))
            ->whereIn('status', ProjectStatus::active())
            ->with(['team.clientProfile', 'members.studentProfile'])
            ->latest('published_at')
            ->first();
    }

    /**
     * Shape the active project and the team on it.
     *
     * @return array<string, mixed>|null
     */
    protected function project(?Project $project, ?Agreement $agreement): ?array
    {
        if ($project === null) {
            return null;
        }

        /*
         * The same Granular Task Completion figures Project Management and the
         * client dashboard draw: tasks the client verified over every task.
         */
        $summary = $agreement === null ? null : app(SummariseProgress::class)->handle($agreement);

        return [
            'title' => $project->title,
            'slug' => $project->slug,
            'client' => $project->team->clientProfile?->business_name ?? $project->team->name,
            'statusLabel' => $project->status->label(),
            /*
             * Null without an agreement — the ring shows a dash rather than a
             * percentage nothing supports. With one, it is the share of tasks
             * the client verified, and the card says "x of y tasks verified"
             * so it cannot be read as the student's own estimate.
             */
            'progress' => $summary['progress'] ?? null,
            'verifiedCount' => $summary['verifiedCount'] ?? null,
            'submittedCount' => $summary['submittedCount'] ?? null,
            'taskCount' => $summary['taskCount'] ?? null,
            'currentPhase' => $summary['currentPhase']['title'] ?? null,
            'nextMilestone' => $summary['nextMilestone'] ?? null,
            'phases' => $summary['phases'] ?? [],
            'dueDate' => $agreement?->ends_on?->format('j M Y'),
            'agreementId' => $agreement?->id,
            'team' => $project->members
                ->map(fn (User $member): array => [
                    'name' => $member->name,
                    'role' => $member->studentProfile?->headline,
                    'isAvailable' => (bool) ($member->studentProfile?->is_available ?? false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Index the agreed milestone dates by the day they fall on.
     *
     * Every month's, not only this one's: the calendar card builds its month
     * grid in the browser and can be paged, so it needs the whole schedule to
     * mark whichever month is shown. Building the grid there also puts
     * "today" on the viewer's own date — the app runs in UTC, so a grid built
     * here showed yesterday as today until 8 AM in the Philippines. Without a
     * signed agreement there are no marks, because a posting carries no dates.
     *
     * A milestone that starts and ends on the same day is one mark, not two —
     * the calendar has one cell to say it with.
     *
     * @return array<string, string>
     */
    protected function milestoneMarks(?Agreement $agreement): array
    {
        if ($agreement === null) {
            return [];
        }

        $marks = [];

        /* The working schedule: planned dates where the student moved a phase, agreed ones otherwise. */
        foreach ($agreement->milestones as $milestone) {
            if ($milestone->scheduledStartsOn() !== null) {
                $marks[$milestone->scheduledStartsOn()->toDateString()] = $milestone->title.' starts';
            }

            if ($milestone->scheduledEndsOn() !== null) {
                $date = $milestone->scheduledEndsOn()->toDateString();

                $marks[$date] = isset($marks[$date])
                    ? $milestone->title
                    : $milestone->title.' due';
            }
        }

        /*
         * Task deadlines, and any date the team is asking to move one to — the
         * same dates Project Management draws, so an approval shows here too.
         */
        foreach ($agreement->milestones as $milestone) {
            foreach ($milestone->tasks as $task) {
                if ($task->due_on !== null && $task->status !== TaskStatus::Verified) {
                    $this->addMark($marks, $task->due_on->toDateString(), $task->title.' due');
                }

                if ($task->pendingDeadlineRequest !== null) {
                    $this->addMark($marks, $task->pendingDeadlineRequest->proposed_on->toDateString(), $task->title.' (new date asked for)');
                }
            }
        }

        return $marks;
    }

    /**
     * Put a line on a calendar day, beside whatever is already there.
     *
     * @param  array<string, string>  $marks
     */
    protected function addMark(array &$marks, string $date, string $label): void
    {
        $marks[$date] = isset($marks[$date]) ? $marks[$date].' · '.$label : $label;
    }

    /**
     * Get the announcements block an administrator maintains.
     *
     * @return array<string, string|null>|null
     */
    protected function announcement(): ?array
    {
        $block = SiteContent::firstWhere('key', SiteContentKey::Announcements);

        if ($block === null || blank($block->body)) {
            return null;
        }

        return [
            'body' => $block->body,
            'updatedAt' => $block->updated_at?->diffForHumans(),
        ];
    }
}
