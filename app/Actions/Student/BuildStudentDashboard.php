<?php

namespace App\Actions\Student;

use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Enums\SiteContentKey;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Gathers everything the student dashboard shows.
 *
 * A posting no longer carries dates of any kind — milestones went first, then
 * the start and delivery dates that replaced them — so there is nothing left to
 * schedule against. The calendar is now a plain month with today marked, and
 * the ring reports how far the project has moved through its lifecycle rather
 * than inventing a percentage from data that does not exist.
 */
class BuildStudentDashboard
{
    /**
     * How far along each status sits, for the ring.
     *
     * Coarse on purpose. It is the only progress signal a posting still
     * carries, and pretending to finer resolution would be a fiction.
     */
    private const STATUS_PROGRESS = [
        ProjectStatus::Open->value => 10,
        ProjectStatus::InProgress->value => 50,
        ProjectStatus::Completed->value => 100,
    ];

    /**
     * Build the dashboard payload for a student.
     *
     * @return array<string, mixed>
     */
    public function handle(User $student, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        return [
            'project' => $this->project($this->activeProject($student)),
            'calendar' => $this->calendar($today),
            'announcement' => $this->announcement(),
        ];
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
    protected function project(?Project $project): ?array
    {
        if ($project === null) {
            return null;
        }

        return [
            'title' => $project->title,
            'slug' => $project->slug,
            'client' => $project->team->clientProfile?->business_name ?? $project->team->name,
            'statusLabel' => $project->status->label(),
            'progress' => self::STATUS_PROGRESS[$project->status->value] ?? null,
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
     * change height between months. Nothing is marked but today — postings
     * carry no dates for it to mark.
     *
     * @return array<string, mixed>
     */
    protected function calendar(Carbon $today): array
    {
        $firstOfMonth = $today->copy()->startOfMonth();
        $cursor = $firstOfMonth->copy()->startOfWeek(Carbon::SUNDAY);

        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $days[] = [
                'day' => $cursor->day,
                'date' => $cursor->toDateString(),
                'isToday' => $cursor->isSameDay($today),
                'isOutsideMonth' => $cursor->month !== $firstOfMonth->month,
            ];

            $cursor->addDay();
        }

        return [
            'label' => $firstOfMonth->format('M Y'),
            'days' => $days,
        ];
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
