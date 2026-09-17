<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use App\Enums\UserRole;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * A thread between one business and one student about one posting.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property int|null $student_team_id
 * @property Carbon|null $last_message_at
 * @property int|null $client_read_message_id
 * @property int|null $student_read_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $student
 * @property-read Collection<int, Message> $messages
 * @property-read Collection<int, User> $members
 * @property-read Team|null $studentTeam
 * @property-read Message|null $latestMessage
 */
#[Fillable([
    'project_id', 'user_id', 'last_message_at',
    'client_read_message_id', 'student_read_message_id',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the posting the thread is about.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the student half of the thread.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The student team this thread belongs to, when there is one.
     *
     * Null means the student is working alone: only they see it from that
     * side. Set, it means the leader brought their team in, and every member
     * reads and writes here exactly as the business team already does.
     */
    public function studentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'student_team_id');
    }

    /**
     * Teammates the team's creator invited into this thread's group chat.
     *
     * Only half the rule: a row counts while the person is still on the
     * thread's team — see groupMembers() and isParticipant().
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot('invited_by')
            ->withTimestamps();
    }

    /**
     * The invited teammates who are still on the thread's team.
     *
     * @return Collection<int, User>
     */
    public function groupMembers(): Collection
    {
        if ($this->student_team_id === null || $this->studentTeam === null) {
            return new Collection;
        }

        return $this->members()
            ->whereHas('teams', fn (Builder $team) => $team->where('teams.id', $this->student_team_id))
            ->orderBy('conversation_members.id')
            ->get();
    }

    /**
     * Whether this thread is a group chat: its team is attached and at least
     * one teammate has been invited in.
     */
    public function isGroup(): bool
    {
        return $this->groupMembers()->isNotEmpty();
    }

    /**
     * Whether the given user may invite teammates into this thread, or take
     * them out: the creator of the thread's team, and only from inside it.
     */
    public function canManageGroup(User $user): bool
    {
        $team = $this->studentTeam;

        return $team !== null
            && $user->ownsTeam($team)
            && $this->isParticipant($user);
    }

    /**
     * Get every message in order.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest('id');
    }

    /**
     * Get every video meeting held on this thread, newest first.
     *
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class)->latest('id');
    }

    /**
     * Get the most recent message, for the thread list preview.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Everyone who may read and write this thread, once each.
     *
     * The same set isParticipant() answers for: the student it belongs to,
     * the teammates invited into its group chat, and the business's team.
     * At most five people — see Team::MAX_MEMBERS.
     *
     * @return SupportCollection<int, User>
     */
    public function participants(): SupportCollection
    {
        return collect([$this->student])
            ->merge($this->groupMembers())
            ->merge($this->project->team->members()->get())
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Determine if the given user may read and write this thread.
     *
     * The student it belongs to, a teammate the team's creator invited in, or
     * anyone on the business's team. An administrator is not a participant —
     * support can read the database, but nobody silently joins a conversation
     * between two other people.
     */
    public function isParticipant(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->isGroupMember($user)) {
            return true;
        }

        return $user->belongsToTeam($this->project->team);
    }

    /**
     * Whether the user was invited into this thread's group chat and is still
     * on its team.
     *
     * Both halves, read live. Being on the team is no longer enough — the
     * creator decides who is in — and an invitation stops counting the moment
     * its holder leaves the team.
     */
    public function isGroupMember(User $user): bool
    {
        $studentTeam = $this->studentTeam;

        if ($studentTeam === null) {
            return false;
        }

        $invited = $this->relationLoaded('members')
            ? $this->members->contains('id', $user->id)
            : $this->members()->whereKey($user->id)->exists();

        return $invited && $user->belongsToTeam($studentTeam);
    }

    /**
     * Attach the student's team to this thread, so its creator can invite
     * teammates in.
     *
     * Attaching lets nobody else in by itself — the creator still invites
     * each person (see members()). Done on open, so a thread started before
     * the team existed picks it up on the next visit.
     *
     * Only for the team's creator. A member's own threads with clients stay
     * between them and the client; a member does not hand their
     * conversations to the team by joining it.
     *
     * Only a team with somebody else in it: there is nobody to invite from a
     * team of one. Read off the membership rather than off is_personal — the
     * team a student is handed at sign up is the team they build with.
     */
    public function adoptStudentTeam(): void
    {
        if ($this->student_team_id !== null) {
            return;
        }

        $student = $this->student;
        $team = $student?->currentTeam;

        if ($team === null || $team->isSolo() || ! $student->ownsTeam($team)) {
            return;
        }

        $this->forceFill(['student_team_id' => $team->id])->save();
    }

    /**
     * Take a student who is no longer on a team out of that team's group
     * chats.
     *
     * Their own threads fall back to them and the client, as
     * JoinTeam::dissolve() does, and their invitations into other threads on
     * the team are deleted. A group chat is the thread's student, invited
     * teammates and the client — at most five people, because the team is
     * capped at Team::MAX_MEMBERS — and a leaver left inside would push it
     * past that as soon as the team filled their seat.
     */
    public static function releaseFromTeam(User $student, Team $team): void
    {
        $ownThreads = static::query()
            ->where('user_id', $student->id)
            ->where('student_team_id', $team->id)
            ->pluck('id');

        DB::table('conversation_members')->whereIn('conversation_id', $ownThreads)->delete();

        static::query()->whereKey($ownThreads)->update(['student_team_id' => null]);

        DB::table('conversation_members')
            ->where('user_id', $student->id)
            ->whereIn('conversation_id', static::query()->select('id')->where('student_team_id', $team->id))
            ->delete();
    }

    /**
     * Get which side of the thread the given user is on.
     *
     * Invited teammates are on the student's side: they read the business's
     * name as the thread's title, and share the student side's read marker.
     * Treating them as the client used to clear the client's unread badge
     * whenever a teammate opened the thread.
     */
    public function sideFor(User $user): UserRole
    {
        if ($this->user_id === $user->id) {
            return UserRole::Student;
        }

        if ($this->student_team_id === null) {
            return UserRole::Client;
        }

        $invited = $this->relationLoaded('members')
            ? $this->members->contains('id', $user->id)
            : $this->members()->whereKey($user->id)->exists();

        return $invited ? UserRole::Student : UserRole::Client;
    }

    /**
     * Determine if there is something the given user has not read.
     *
     * Compared by message id, not by timestamp. Datetimes are stored to the
     * second, so a reply arriving in the same second as the reader's last
     * visit is indistinguishable from one already seen.
     */
    public function isUnreadFor(User $user): bool
    {
        $latest = $this->latestMessage;

        if ($latest === null) {
            return false;
        }

        /* Your own message is never news to you. */
        if ($latest->user_id === $user->id) {
            return false;
        }

        return $latest->id > (int) $this->readMarkerFor($user);
    }

    /**
     * Mark the thread read for whichever side the user is on.
     */
    public function markReadFor(User $user): void
    {
        $latest = $this->latestMessage;

        if ($latest === null) {
            return;
        }

        $this->forceFill([$this->readColumnFor($user) => $latest->id])->save();
    }

    /**
     * Get the id of the last message the given user has seen.
     */
    public function readMarkerFor(User $user): ?int
    {
        return $this->{$this->readColumnFor($user)};
    }

    /**
     * Get the read-marker column for whichever side the user is on.
     */
    protected function readColumnFor(User $user): string
    {
        return $this->sideFor($user) === UserRole::Student
            ? 'student_read_message_id'
            : 'client_read_message_id';
    }

    /**
     * Scope to the threads the given user takes part in.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forParticipant(Builder $query, User $user): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('user_id', $user->id)
            /* Invited into the group chat, and still on its team. */
            ->orWhere(fn (Builder $group) => $group
                ->whereHas('members', fn (Builder $member) => $member
                    ->where('users.id', $user->id))
                ->whereHas('studentTeam.members', fn (Builder $member) => $member
                    ->where('users.id', $user->id)))
            ->orWhereHas('project.team.members', fn (Builder $member) => $member
                ->where('users.id', $user->id)));
    }

    /**
     * The threads this person should still be shown.
     *
     * A signed agreement closes the pairing off. From that moment the student
     * is building for one business and the business is building with one
     * student, so every thread the two of them opened while shopping around
     * stops being a live conversation — the other applicants a client spoke
     * to, and the other clients a student was weighing up.
     *
     * Nothing is deleted. The rows and their messages stay exactly where they
     * are and come back on their own once the agreement is no longer active,
     * which is what makes this safe to apply to a thread somebody has already
     * had a long exchange in.
     *
     * Two exclusions, one for each side of the pairing:
     *
     *   1. the posting is spoken for, and not by this thread's student — the
     *      client stops seeing the applicants they did not choose;
     *   2. this thread's student is spoken for, on some other posting — the
     *      student stops seeing the clients they did not go with.
     *
     * Written as NOT EXISTS rather than whereDoesntHave so both read against
     * the conversation's own columns in one query; the inbox draws every
     * thread at once and this must not become a query per row.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->forParticipant($user)
            ->whereNotExists(fn (QueryBuilder $sub) => $sub
                ->selectRaw('1')
                ->from('agreements')
                ->whereColumn('agreements.project_id', 'conversations.project_id')
                ->whereColumn('agreements.student_id', '!=', 'conversations.user_id')
                ->where('agreements.status', AgreementStatus::Active))
            ->whereNotExists(fn (QueryBuilder $sub) => $sub
                ->selectRaw('1')
                ->from('agreements')
                ->whereColumn('agreements.student_id', 'conversations.user_id')
                ->whereColumn('agreements.project_id', '!=', 'conversations.project_id')
                ->where('agreements.status', AgreementStatus::Active));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'client_read_message_id' => 'integer',
            'student_read_message_id' => 'integer',
        ];
    }
}
