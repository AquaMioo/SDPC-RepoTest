<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CollaboratingTeams;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\GiveOwnTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of the user's teams.
     */
    public function index(Request $request, CollaboratingTeams $collaborating): Response
    {
        return Inertia::render('teams/index', $this->teamsPage($request->user(), $collaborating));
    }

    /**
     * What the Team page shows.
     *
     * Also sent with the team settings screen, which opens as a floating
     * window over the Team page rather than as a page of its own (QA
     * 2026-09-19) — so the page behind it is the real one, drawn from the
     * same payload.
     *
     * @return array<string, mixed>
     */
    protected function teamsPage(User $user, CollaboratingTeams $collaborating): array
    {
        return [
            'teams' => $user->toUserTeams(includeCurrent: true),
            /*
             * Nobody creates a team here any more.
             *
             * Every account is handed one at sign up, and nothing ever stopped
             * that team being renamed or invited into — so it was already the
             * student's team in everything but name, and a "New team" button
             * beside it offered a second one nobody needed. Capping creation
             * at one meant nothing while everyone began with one they had not
             * created.
             *
             * So the team you have is the team you build with: rename it,
             * invite up to three others, and it becomes a group the moment one
             * of them accepts. One team per student is now true by
             * construction rather than by a rule that had to be enforced.
             */
            'canCreateTeam' => false,
            'createBlockedBecause' => $user->isClient()
                ? null
                : 'You can be on one team at a time. If you join another team, it replaces yours, as long as nobody else has joined yours yet.',
            'membership' => $user->isStudent() ? $this->membership($user) : null,
            'collaboratingTeams' => $user->isClient()
                ? $collaborating->handle($user)
                : [],
        ];
    }

    /**
     * Which of the two ways the student is on their team.
     *
     * "Created": the team is theirs, and anyone else on it joined them — they
     * stay its lead. "Joined": they accepted somebody else's invitation, and
     * the team they had on their own was dissolved when they did.
     *
     * @return array{kind: 'created'|'joined', team: string, lead: string|null, memberCount: int}|null
     */
    protected function membership(User $user): ?array
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return null;
        }

        $ownsIt = $user->ownsTeam($team);

        return [
            'kind' => $ownsIt ? 'created' : 'joined',
            'team' => $team->name,
            'lead' => $ownsIt
                ? null
                : $team->members()->wherePivot('role', TeamRole::Owner->value)->value('name'),
            'memberCount' => $team->members()->count(),
        ];
    }

    /**
     * Refuse to raise a second team.
     *
     * Nobody creates one on this platform. Every account is given a team at
     * registration, and that team was always renameable and invitable — so it
     * was already the team its owner builds with, and a second one served no
     * purpose. A client's is their business, which owns the postings;
     * a student's is the group they take a project on with.
     *
     * The endpoint is kept rather than deleted so an old bookmark, a stale
     * bundle or a hand-made request gets an explanation instead of a 404 that
     * looks like a fault.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        throw ValidationException::withMessages([
            'name' => $request->user()->isClient()
                ? 'A business has one team — the business itself, created when you registered.'
                : 'You already have a team: the one you were given when you signed up. Open it to rename it and invite people in.',
        ]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team, CollaboratingTeams $collaborating): Response
    {
        $user = $request->user();

        return Inertia::render('teams/edit', [
            // The Team page, drawn behind the floating settings window.
            'background' => $this->teamsPage($user, $collaborating),
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()->get()->map(function (User $member) {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->validated('name')]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team), 403);

        $request->user()->switchTeam($team);

        return back();
    }

    /**
     * Leave the specified team.
     */
    public function leave(Request $request, Team $team, GiveOwnTeam $giveOwnTeam): RedirectResponse
    {
        Gate::authorize('leave', $team);

        $user = $request->user();
        $wasCurrent = $user->isCurrentTeam($team);

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        /* Their own threads stop being this team's group chat. */
        Conversation::releaseFromTeam($user, $team);

        /*
         * Joining replaced the student's own team, so leaving the one they
         * joined hands them a fresh one — a student is always on exactly one.
         */
        if ($wasCurrent || $user->teams()->doesntExist()) {
            $giveOwnTeam->handle($user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the team ":name"', ['name' => $team->name])]);

        return to_route('teams.index');
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }
}
