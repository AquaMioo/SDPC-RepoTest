<?php

namespace App\Actions\Student;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\SiteContentKey;
use App\Models\Agreement;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Gathers everything the student dashboard shows.
 *
 * A posting carries no dates and no money — it is a brief. The schedule lives
 * on the signed agreement instead, so the ring reports the milestones two
 * people actually moved and the calendar marks the phase dates they agreed.
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
    public function handle(User $student, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $project = $this->activeProject($student);
        $agreement = $this->activeAgreement($student, $project);

        return [
            'project' => $this->project($project, $agreement),
            'calendar' => $this->calendar($today, $agreement),
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
            ->with('milestones')
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

        return [
            'title' => $project->title,
            'slug' => $project->slug,
            'client' => $project->team->clientProfile?->business_name ?? $project->team->name,
            'statusLabel' => $project->status->label(),
            /*
             * The agreement's own figure, averaged across milestones that a
             * person moved by hand. Null without one — the ring shows a dash
             * rather than a percentage nothing supports.
             */
            'progress' => $agreement?->progress(),
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
     * Build the month grid the calendar card renders.
     *
     * Six weeks from the Sunday on or before the first, so the card does not
     * change height between months. Milestone start and end dates are marked
     * from the signed agreement; without one the month is plain, because a
     * posting still carries no dates of its own.
     *
     * @return array<string, mixed>
     */
    protected function calendar(Carbon $today, ?Agreement $agreement): array
    {
        $marks = $this->milestoneMarks($agreement);

        $firstOfMonth = $today->copy()->startOfMonth();
        $cursor = $firstOfMonth->copy()->startOfWeek(Carbon::SUNDAY);

        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $date = $cursor->toDateString();

            $days[] = [
                'day' => $cursor->day,
                'date' => $date,
                'isToday' => $cursor->isSameDay($today),
                'isOutsideMonth' => $cursor->month !== $firstOfMonth->month,
                'milestone' => $marks[$date] ?? null,
            ];

            $cursor->addDay();
        }

        return [
            'label' => $firstOfMonth->format('M Y'),
            'days' => $days,
        ];
    }

    /**
     * Index the agreed milestone dates by the day they fall on.
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

        foreach ($agreement->milestones as $milestone) {
            if ($milestone->starts_on !== null) {
                $marks[$milestone->starts_on->toDateString()] = $milestone->title.' starts';
            }

            if ($milestone->ends_on !== null) {
                $date = $milestone->ends_on->toDateString();

                $marks[$date] = isset($marks[$date])
                    ? $milestone->title
                    : $milestone->title.' due';
            }
        }

        return $marks;
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
