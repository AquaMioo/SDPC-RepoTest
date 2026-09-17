<?php

namespace App\Http\Middleware;

use App\Actions\Notifications\PresentNotification;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // The layouts pick their palette and navigation from these, so
                // they are shared rather than passed page by page.
                'role' => $user?->role,
                'status' => $user?->status,
                'isAdmin' => (bool) $user?->isAdmin(),
                // Drawn by the header on every screen, and the column it comes
                // from is not the one the model exposes.
                'avatarUrl' => $user?->avatarUrl(),
            ],
            /*
             * The header's chat icon carries this on every screen, so it is
             * shared rather than passed page by page. Closured so the query
             * only runs for a full page load, not a partial reload.
             *
             * latestMessage is eager loaded because isUnreadFor() reads it on
             * every thread. Without it this is one query per conversation the
             * user takes part in, on every full page load of every screen —
             * the count cannot be pushed into SQL instead, since "unread"
             * also depends on who sent the last message.
             */
            'unreadMessages' => fn (): int => $user === null
                ? 0
                : Conversation::query()
                    /*
                     * visibleTo, so the badge counts the same threads the
                     * inbox draws. On forParticipant it would keep counting
                     * unread lines in the threads a signed agreement has since
                     * closed off — a number the screen could not account for.
                     */
                    ->visibleTo($user)
                    // members: which side of a group chat the user is on, without a query per thread.
                    ->with(['latestMessage', 'members'])
                    ->get()
                    ->filter(fn (Conversation $thread) => $thread->isUnreadFor($user))
                    ->count(),
            /*
             * The bell carries this on every screen, for the same reason the
             * chat icon does. Closured so the count is not queried on a
             * partial reload that never draws the header.
             */
            'unreadNotifications' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
            /*
             * The rows behind the bell's menu, so it can open without a
             * request of its own. Six because the menu shows five and the
             * sixth is what tells it there is more to see.
             *
             * Empty for anyone without a current team — the administrator, who
             * has none. PresentNotification builds team-scoped URLs, so there
             * is nothing it could point at for an account outside a team.
             */
            'recentNotifications' => function () use ($user): array {
                $team = $user?->currentTeam;

                if ($user === null || $team === null) {
                    return [];
                }

                $presenter = app(PresentNotification::class);

                return $user->notifications()
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (DatabaseNotification $row): array => $presenter->handle($row, $team))
                    ->all();
            },
            /*
             * The money side ships switched off, and the nav has to know:
             * without this the Transaction link would point at routes that
             * 404 by design. See config/billing.php.
             */
            'billingEnabled' => (bool) config('billing.enabled'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}
