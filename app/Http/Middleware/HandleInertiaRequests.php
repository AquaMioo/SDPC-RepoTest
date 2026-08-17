<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Illuminate\Http\Request;
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
            ],
            /*
             * The header's chat icon carries this on every screen, so it is
             * shared rather than passed page by page. Closured so the query
             * only runs for a full page load, not a partial reload.
             */
            'unreadMessages' => fn (): int => $user === null
                ? 0
                : Conversation::query()
                    ->forParticipant($user)
                    ->get()
                    ->filter(fn (Conversation $thread) => $thread->isUnreadFor($user))
                    ->count(),
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
