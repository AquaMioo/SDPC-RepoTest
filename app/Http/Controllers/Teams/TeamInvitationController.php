<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\JoinTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTeamInvitationRequest $request, Team $team, JoinTeam $joinTeam): RedirectResponse
    {
        Gate::authorize('inviteMember', $team);

        /*
         * The address belongs to a student account — CreateTeamInvitationRequest
         * refuses anything else (App\Rules\RegisteredStudent), so there is
         * always somebody here to check and, below, to notify.
         *
         * One team per student. An account that could never accept is told
         * now, rather than their invitation failing when they try.
         */
        $invitee = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($request->validated('email'))])
            ->firstOrFail();

        $refusal = $joinTeam->refusal($invitee, $team);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['email' => $refusal]);
        }

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

        /* Their inbox and their bell: the row is what makes it openable later. */
        $invitee->notify(new TeamInvitationNotification($invitation));

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
    public function accept(RespondToTeamInvitationRequest $request, TeamInvitation $invitation, JoinTeam $joinTeam): RedirectResponse
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

        /*
         * One team per student: joining replaces a team they have on their
         * own, and is refused while they lead a group or sit on another team.
         * See App\Actions\Teams\JoinTeam.
         */
        $team = $joinTeam->handle($user, $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You joined :team. It is your team now.', ['team' => $team->name])]);

        /* Named explicitly: the team the URL defaults were built from may just have been dissolved. */
        return to_route('dashboard', ['current_team' => $team->slug]);
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
