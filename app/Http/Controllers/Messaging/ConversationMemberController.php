<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\NotifyMemberAdded;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Who is in a thread's group chat.
 *
 * Being on the student's team is not enough to read the thread. The team's
 * creator invites teammates one by one and can take them out again; the
 * student the thread belongs to and the business are always in. Nobody else
 * — not the invited teammates, not the client — may change the list.
 */
class ConversationMemberController extends Controller
{
    public function __construct(private readonly NotifyMemberAdded $notifyMemberAdded) {}

    /**
     * Invite a teammate into the group chat.
     */
    public function store(Request $request, Team $currentTeam, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->canManageGroup($user), HttpResponse::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $member = User::find($validated['user_id']);
        $team = $conversation->studentTeam;

        if ($member === null || $member->is($user) || $conversation->user_id === $member->id || ! $member->belongsToTeam($team)) {
            throw ValidationException::withMessages([
                'user_id' => __('Only teammates on :team who are not in this chat yet can be invited.', ['team' => $team->name]),
            ]);
        }

        if ($conversation->members()->whereKey($member->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __(':name is already in this chat.', ['name' => $member->name]),
            ]);
        }

        $conversation->members()->attach($member->id, ['invited_by' => $user->id]);

        $this->notifyMemberAdded->handle($conversation, $member, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name can now read and write this chat.', ['name' => $member->name]),
        ]);

        return back();
    }

    /**
     * Take a teammate out of the group chat. The thread's own student and the
     * creator themselves cannot be taken out.
     */
    public function destroy(Request $request, Team $currentTeam, Conversation $conversation, User $member): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->canManageGroup($user), HttpResponse::HTTP_FORBIDDEN);

        if ($member->is($user) || $conversation->user_id === $member->id) {
            throw ValidationException::withMessages([
                'member' => __(':name cannot be removed from this chat.', ['name' => $member->name]),
            ]);
        }

        abort_unless($conversation->members()->detach($member->id) > 0, HttpResponse::HTTP_NOT_FOUND);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was removed from this chat.', ['name' => $member->name]),
        ]);

        return back();
    }
}
