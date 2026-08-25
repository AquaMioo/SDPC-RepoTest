<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ClientProfile;
use App\Models\Project;
use App\Models\StudentProfile;
use App\Models\Testimonial;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public landing page.
 *
 * Every number on this screen is counted from the database rather than
 * written into the markup. A platform that has not run a project yet should
 * say so — an invented figure on the one page the whole town can see is the
 * kind of thing that gets noticed in a panel.
 */
class HomeController extends Controller
{
    /**
     * Show the landing page with its live counts.
     */
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'stats' => $this->stats(),
            'testimonials' => $this->testimonials(),
        ]);
    }

    /**
     * Get what clients have actually said, newest first.
     *
     * Returns an empty list until a business writes one, and the section
     * stays off the page while it is empty. There is no sample copy to fall
     * back on — an invented quote attributed to a named local business is a
     * different kind of wrong from an invented statistic.
     *
     * @return list<array{quote: string, name: string, role: string|null}>
     */
    protected function testimonials(): array
    {
        return Testimonial::query()
            ->publishable()
            ->with(['team.clientProfile', 'author'])
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Testimonial $testimonial): array => [
                'quote' => $testimonial->body,
                'name' => $testimonial->author?->name
                    ?? $testimonial->team->clientProfile->owner_name
                    ?? $testimonial->team->clientProfile->business_name,
                'role' => collect([
                    $testimonial->author_title,
                    $testimonial->team->clientProfile->business_name,
                ])->filter()->implode(', ') ?: null,
            ])
            ->all();
    }

    /**
     * Build the stat band's four figures.
     *
     * A null reads as "nothing to report yet" and renders as a dash, which is
     * different from a zero: no project has been marked complete is a fact,
     * while no one has rated anything is an absence of data.
     *
     * @return array{students: int, projectsCompleted: int, clientSatisfaction: int|null, clients: int, studentRating: array{average: float, rated: int}|null}
     */
    protected function stats(): array
    {
        return [
            'students' => User::query()
                ->where('role', UserRole::Student)
                ->where('status', '!=', UserStatus::Deactivated)
                ->count(),

            'projectsCompleted' => Project::query()
                ->where('status', ProjectStatus::Completed)
                ->count(),

            /*
             * Nothing collects a satisfaction score yet. Rather than dress up
             * a zero as 0%, the band shows a dash until there is something to
             * average.
             */
            'clientSatisfaction' => null,

            /*
             * Counted as businesses, not user accounts: a client is a team, so
             * two staff from the same shop are one local client. The whereHas
             * matters — Team soft deletes, so a closed business leaves its
             * profile row behind and would otherwise be counted forever.
             */
            'clients' => ClientProfile::query()->whereHas('team')->count(),

            'studentRating' => $this->studentRating(),
        ];
    }

    /**
     * The average rating across students who have one, and how many that is.
     *
     * Averaged over rated profiles only. rating_average defaults to 0, so
     * including the unrated would drag the figure toward zero and report it as
     * a poor score rather than as an absent one.
     *
     * Null until somebody has been rated at all, which is what the hero checks
     * before it draws stars. Nothing writes this column yet — no rating
     * feature exists — so on a fresh database the row is simply absent rather
     * than showing a number the platform cannot stand behind.
     *
     * @return array{average: float, rated: int}|null
     */
    protected function studentRating(): ?array
    {
        $rated = StudentProfile::query()
            ->where('rating_average', '>', 0)
            ->whereHas('user', fn ($query) => $query
                ->where('status', '!=', UserStatus::Deactivated));

        $count = (clone $rated)->count();

        if ($count === 0) {
            return null;
        }

        return [
            'average' => round((float) (clone $rated)->avg('rating_average'), 1),
            'rated' => $count,
        ];
    }
}
