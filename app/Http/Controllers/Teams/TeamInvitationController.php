<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        /*
         * Seats, not members: an invitation already sent is a seat promised.
         * See Team::remainingSeats().
         */
        if ($team->remainingSeats() < 1) {
            throw ValidationException::withMessages([
                'email' => 'This team is full. A team holds '.Team::MAX_MEMBERS.' people including you — remove somebody, or cancel an invitation you have already sent, to make room.',
            ]);
        }

        $invitation = $team->invitations()->create([
            'email' => $request->validated('email'),
            'role' => TeamRole::from($request->validated('role')),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        /*
         * Invitations are addressed to an email, which may or may not belong
         * to somebody who has registered. When it does, notify the account so
         * the invitation reaches their bell as well as their inbox; when it
         * does not, there is nowhere to store a row and mail is all there is.
         */
        $invitee = User::query()->firstWhere('email', $invitation->email);

        $invitee !== null
            ? $invitee->notify(new TeamInvitationNotification($invitation))
            : Notification::route('mail', $invitation->email)
                ->notify(new TeamInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Team $team, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        /*
         * Checked again here, not only when the invitation was sent. An
         * invitation can outlive the room it was sent for — somebody else
         * accepts first, or the leader adds people directly — and the cap has
         * to hold at the moment the seat is actually taken.
         */
        if ($invitation->team->isFull()) {
            throw ValidationException::withMessages([
                'invitation' => 'That team is already full, so this invitation can no longer be accepted. Ask whoever invited you to make room and send it again.',
            ]);
        }

        DB::transaction(function () use ($user, $invitation) {
            $team = $invitation->team;

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}
