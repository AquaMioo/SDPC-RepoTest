<?php

namespace App\Http\Controllers\Notifications;

use App\Actions\Notifications\PresentNotification;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The notification centre, shared by both modules.
 *
 * Seven notification classes already wrote rows nobody could read — the bell
 * was a tooltip saying the module had not arrived. This is that module: the
 * same events, finally legible.
 *
 * There is no authorisation logic here beyond the relation itself. A
 * notification belongs to exactly one notifiable, and every query starts from
 * the signed-in user, so there is no path to somebody else's row to guard.
 */
class NotificationController extends Controller
{
    /**
     * Show everything sent to the signed-in account, newest first.
     */
    public function index(Request $request, Team $currentTeam, PresentNotification $presenter): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(100)->get();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications
                ->map(fn (DatabaseNotification $notification) => $presenter->handle($notification, $currentTeam))
                ->all(),
            /*
             * Counted against the whole table, not the hundred rows drawn
             * above. Counting the slice made this disagree with the bell in
             * the header — which counts all of them — for anybody holding
             * more than a hundred notifications, and "mark all read" clears
             * more than the number next to it claimed.
             */
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification read.
     *
     * Scoped through the user's own relation rather than found globally, so a
     * guessed id belonging to somebody else is a 404 and not a silent write.
     */
    public function read(Request $request, Team $currentTeam, string $notification): RedirectResponse
    {
        $row = $request->user()->notifications()->find($notification);

        abort_if($row === null, HttpResponse::HTTP_NOT_FOUND);

        $row->markAsRead();

        return back();
    }

    /**
     * Mark everything read.
     */
    public function readAll(Request $request, Team $currentTeam): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
