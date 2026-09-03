<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
     * Determine if the given user may read and write this thread.
     *
     * The student it belongs to, or anyone on the business's team. An
     * administrator is not a participant — support can read the database, but
     * nobody silently joins a conversation between two other people.
     */
    public function isParticipant(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        /*
         * The student's team, if the thread has one. Membership is read live
         * rather than copied in, so somebody the leader invites tomorrow can
         * read the thread tomorrow — the invitation is the existing team
         * invitation, and this is all that has to notice it.
         */
        $studentTeam = $this->studentTeam;

        if ($studentTeam !== null && $user->belongsToTeam($studentTeam)) {
            return true;
        }

        return $user->belongsToTeam($this->project->team);
    }

    /**
     * Attach the student's team to this thread, if they have one and it has
     * none yet.
     *
     * Threads opened before the student formed a team would otherwise never
     * gain one, and the leader would invite people into a group that could
     * not see the conversation it was for. Adopting once, on open, fixes
     * those without a backfill nobody would remember to run.
     *
     * Only a real team: a personal team is one person, and recording it would
     * say nothing user_id does not already say — while making the thread look
     * like a group that it is not.
     */
    public function adoptStudentTeam(): void
    {
        if ($this->student_team_id !== null) {
            return;
        }

        $team = $this->student?->currentTeam;

        if ($team === null || $team->is_personal) {
            return;
        }

        $this->forceFill(['student_team_id' => $team->id])->save();
    }

    /**
     * Get which side of the thread the given user is on.
     */
    public function sideFor(User $user): UserRole
    {
        return $this->user_id === $user->id
            ? UserRole::Student
            : UserRole::Client;
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
            ->orWhereHas('studentTeam.members', fn (Builder $member) => $member
                ->where('users.id', $user->id))
            ->orWhereHas('project.team.members', fn (Builder $member) => $member
                ->where('users.id', $user->id)));
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
