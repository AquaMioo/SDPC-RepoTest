<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\AnnounceMessage;
use App\Actions\Messaging\NotifyOfMessage;
use App\Actions\Messaging\NotifyTeamJoined;
use App\Enums\MilestoneStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\EditMessageRequest;
use App\Http\Requests\Messaging\ReactToMessageRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\AgreementMilestone;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Messaging, shared by both modules.
 *
 * A thread belongs to a posting and a student, and only exists where an
 * application already links the two. That single rule is the whole
 * authorisation model: there is no way to open a conversation with someone you
 * have no dealings with.
 */
class ConversationController extends Controller
{
    public function __construct(
        private readonly AnnounceMessage $announce,
        private readonly NotifyOfMessage $notify,
        private readonly NotifyTeamJoined $notifyTeamJoined,
    ) {}

    /**
     * Serve one message's picture, to somebody entitled to read the thread.
     *
     * Storage::disk('public')->url() handed out a plain static path, and the
     * web server answered it with no session and no check — a picture sent
     * inside a private conversation was readable by anybody holding the URL,
     * signed out. This is the same file behind the same gate as the words
     * around it.
     */
    public function image(
        Request $request,
        Team $currentTeam,
        Conversation $conversation,
        Message $message,
    ): StreamedResponse {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        // A message id from another thread must not resolve here.
        abort_unless($message->conversation_id === $conversation->id, HttpResponse::HTTP_NOT_FOUND);

        abort_if($message->attachment_path === null, HttpResponse::HTTP_NOT_FOUND);
        abort_if($message->isRemoved(), HttpResponse::HTTP_NOT_FOUND);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($message->attachment_path), HttpResponse::HTTP_NOT_FOUND);

        /*
         * private, and no store: this is somebody's conversation, and a shared
         * cache holding it would undo the check above for the next reader.
         */
        return $disk->response($message->attachment_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * The state of the work this thread is about.
     *
     * Null when there is no agreement yet: two people can be talking well
     * before anything is signed, and an empty progress ring beside that
     * conversation would be reporting on something that does not exist.
     *
     * @return array<string, mixed>|null
     */
    protected function intel(Conversation $conversation): ?array
    {
        $agreement = $conversation->project->agreements()
            ->with('milestones')
            ->latest('id')
            ->first();

        if ($agreement === null || $agreement->milestones->isEmpty()) {
            return null;
        }

        /* The one being worked on: the first the client has not accepted. */
        $current = $agreement->milestones
            ->sortBy('position')
            ->firstWhere(fn (AgreementMilestone $milestone): bool => $milestone->status !== MilestoneStatus::Approved);

        $open = $agreement->milestones
            ->filter(fn (AgreementMilestone $milestone): bool => $milestone->status !== MilestoneStatus::Approved)
            ->count();

        return [
            'title' => $current?->title,
            'progress' => $agreement->progress(),
            'due' => $current?->ends_on?->format('j M'),
            'open' => $open,
            'reference' => $agreement->reference,
        ];
    }

    /**
     * Show the inbox, with one thread open.
     */
    public function index(Request $request, Team $currentTeam, ?Conversation $conversation = null): Response|RedirectResponse
    {
        $user = $request->user();

        /*
         * visibleTo rather than forParticipant: a signed agreement closes the
         * pairing off, and the threads either side opened while shopping
         * around drop out of the inbox until it is finished. Nothing is
         * deleted — see Conversation::visibleTo().
         */
        $threads = Conversation::query()
            ->visibleTo($user)
            ->with(['project.team.clientProfile', 'student', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $active = $conversation ?? $threads->first();

        if ($active !== null) {
            abort_unless($active->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

            /*
             * A thread that has dropped out of the list is not opened by its
             * own URL either — but this is a redirect and not a 404.
             *
             * 404 was wrong, and wrong in a way that cost an evening. The
             * thread exists, the person asking is a participant of it, and the
             * only reason it is not on screen is a rule the platform applied
             * on their behalf. "Not found" tells them their messaging is
             * broken. It fires on an ordinary bookmark, on the back button,
             * and on any link written before the contract was signed — so it
             * looked like the inbox failing at random rather than one thread
             * being closed off on purpose.
             */
            if (! $threads->contains($active->getKey())) {
                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => __('That conversation is closed while :name is under contract elsewhere. It comes back when that build is finished.', [
                        'name' => $active->student->name,
                    ]),
                ]);

                return redirect()->route('messages.index', [
                    'current_team' => $currentTeam->slug,
                ]);
            }

            /*
             * A thread opened before the student had a team would never gain
             * one, and the leader would be inviting people into a group that
             * could not see the conversation it was for.
             */
            $active->adoptStudentTeam();

            // Reactions come with the messages: without this the summary runs
            // a query per bubble.
            $active->load(['messages.sender', 'messages.reactions', 'project.team.clientProfile', 'student', 'studentTeam']);
            $active->markReadFor($user);
        }

        return Inertia::render('messaging/index', [
            /*
             * Whether a call can be placed at all. The screen hides the button
             * rather than offering one that 404s — MeetingController gates on
             * the same three values.
             */
            'videoEnabled' => (bool) config('agora.enabled')
                && filled(config('agora.app_id'))
                && filled(config('agora.app_certificate')),
            /*
             * The group-chat control at the foot of the thread list.
             *
             * Offered to the student a thread belongs to and to nobody else:
             * the client cannot pull somebody else's team into it, and a team
             * member reading a thread already brought in has none of their own
             * to add. `hasTeam` is sent separately from `canFormGroup` so the
             * button can say WHY it is unavailable rather than vanishing —
             * "you have no team yet" is the answer somebody needs, and a
             * missing button answers nothing.
             */
            'group' => [
                'canFormGroup' => $active !== null
                    && $active->user_id === $user->id
                    && $active->student_team_id === null,
                'hasTeam' => $user->currentTeam !== null
                    && ! $user->currentTeam->isSolo(),
                'teamName' => $user->currentTeam?->isSolo() === false
                    ? $user->currentTeam->name
                    : null,
                'isGroup' => $active?->student_team_id !== null,
                'groupName' => $active?->studentTeam?->name,
            ],
            'threads' => $threads->map(fn (Conversation $thread) => [
                'id' => $thread->id,
                'title' => $this->counterpartName($thread, $user),
                'subtitle' => $thread->project->title,
                'preview' => str($thread->latestMessage?->body ?? '')->limit(60)->toString(),
                'at' => $thread->last_message_at?->diffForHumans(short: true),
                'isUnread' => $thread->isUnreadFor($user),
                'isActive' => $active !== null && $thread->id === $active->id,
            ])->values()->all(),

            'active' => $active === null ? null : [
                'id' => $active->id,
                'title' => $this->counterpartName($active, $user),
                'project' => $active->project->title,
                /* Booked calls nobody has joined yet, soonest first. */
                'meetings' => $active->meetings()->upcoming()->get()
                    ->map(fn (Meeting $meeting) => [
                        'id' => $meeting->id,
                        'scheduledAt' => $meeting->scheduled_at?->toIso8601String(),
                        'scheduledFor' => $meeting->scheduled_at?->diffForHumans(),
                        'isMine' => $meeting->created_by === $user->id,
                    ])->values()->all(),
                'messages' => $active->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'author' => $message->sender?->name ?? 'Removed account',
                    'isMine' => $message->user_id === $user->id,
                    'at' => $message->created_at?->diffForHumans(short: true),
                    'isEdited' => $message->isEdited(),
                    'isRemoved' => $message->isRemoved(),
                    // The client counts down against this rather than being
                    // told "too late" only on the next reload.
                    'editableUntil' => $message->editableUntilMs(),
                    'imageUrl' => $message->attachment_path === null
                        ? null
                        : route('messages.image', [
                            'current_team' => $currentTeam,
                            'conversation' => $active,
                            'message' => $message,
                        ]),
                    'reactions' => $message->reactionSummary($user),
                ])->values()->all(),
                /*
                 * What the thread is actually about, beside it. Every figure
                 * here is read off the agreement — there is no panel of
                 * "recent files" or "shared links" because the platform
                 * stores neither, and a panel of invented rows is worse than
                 * no panel at all.
                 */
                'intel' => $this->intel($active),
                // The picker's buttons, so the set lives in one place.
                'reactionChoices' => MessageReaction::ALLOWED,
            ],
        ]);
    }

    /**
     * Open — or reopen — the thread for a posting and a student.
     *
     * Called from the applicants screen on the client side and from a posting
     * on the student side, so neither has to know whether a thread exists yet.
     */
    /**
     * Bring the student's team into a thread.
     *
     * This is the whole of "group chat" on this platform: a thread carrying a
     * student_team_id is read and written by every member of that team exactly
     * as the business's side already is — Conversation::isParticipant and the
     * forParticipant scope both check it. So the client the leader was talking
     * to becomes the team's client, in the thread that already holds the
     * history, rather than a second thread being opened beside it.
     *
     * Only the student the thread belongs to may do it. A client cannot pull
     * somebody else's team into their own conversation, and a team member who
     * is not the thread's student has no team of their own to bring.
     */
    public function formGroup(Request $request, Team $currentTeam, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->user_id === $user->id, HttpResponse::HTTP_FORBIDDEN);

        $team = $user->currentTeam;

        /*
         * A team of one is not a group. Read off the membership rather than
         * is_personal — the team a student is handed at sign up is the team
         * they build with, and it counts the moment somebody joins it.
         */
        if ($team === null || $team->isSolo()) {
            throw ValidationException::withMessages([
                'team' => 'Invite somebody to your team first — a group chat needs more than one person. You can invite from Team in the header.',
            ]);
        }

        $conversation->forceFill(['student_team_id' => $team->id])->save();

        /* Both other sides hear about it: the client, and the teammates now in the thread. */
        $this->notifyTeamJoined->handle($conversation->fresh(['project.team', 'studentTeam']), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':team can now read and write this thread.', ['team' => $team->name]),
        ]);

        return back();
    }

    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $student = User::findOrFail($validated['user_id']);

        /*
         * The application is the introduction. Without one there is no reason
         * for these two accounts to be able to write to each other, so there
         * is no thread to open.
         */
        abort_unless(
            Application::query()
                ->where('project_id', $project->id)
                ->where('user_id', $student->id)
                ->exists(),
            HttpResponse::HTTP_FORBIDDEN,
        );

        // And the person asking has to be one of the two sides.
        abort_unless(
            $student->id === $user->id || $user->belongsToTeam($project->team),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $conversation = Conversation::firstOrCreate([
            'project_id' => $project->id,
            'user_id' => $student->id,
        ]);

        /* Whoever the student is working with comes in with them. */
        $conversation->adoptStudentTeam();

        return redirect()->route('messages.show', [
            'current_team' => $currentTeam,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Post a message into a thread.
     */
    public function send(SendMessageRequest $request, Team $currentTeam, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        /*
         * Read before anything is written. Once the new message exists the
         * thread is unread by definition, and the answer decides whether this
         * is a thread waking up — worth a bell — or the next line of a
         * conversation the other side has yet to catch up on.
         */
        $wasAlreadyUnread = $this->notify->wasAlreadyUnread($conversation, $user);

        // Stored before the transaction: writing the file is not something a
        // rollback could undo anyway, and a failed upload should stop here.
        $attachment = $request->hasFile('image')
            ? $request->file('image')->store('message-images/'.$conversation->id, 'public')
            : null;

        $message = DB::transaction(function () use ($conversation, $user, $request, $attachment): Message {
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $request->validated('body'),
                'attachment_path' => $attachment,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            /*
             * Sending counts as reading your own side, so a thread never comes
             * back unread to the person who just wrote in it.
             */
            $conversation->load('latestMessage');
            $conversation->markReadFor($user);

            return $message;
        });

        /*
         * Broadcast after the transaction commits. Firing inside it would let
         * the other side be told about a message that a rollback then undid.
         *
         * Through AnnounceMessage rather than dispatched directly: the message
         * is already saved, so a broadcaster that is down must not turn a
         * delivered message into an error page.
         */
        $this->announce->handle($message);
        $this->notify->handle($message, $wasAlreadyUnread);

        return back();
    }

    /**
     * Change the wording of a message already sent.
     *
     * The sender's alone: the other side of a thread must never be able to
     * rewrite what was said to them. A removed message is past editing.
     */
    public function edit(
        EditMessageRequest $request,
        Team $currentTeam,
        Conversation $conversation,
        Message $message,
    ): RedirectResponse {
        $this->authoriseOwnMessage($request->user(), $conversation, $message);

        abort_if($message->isRemoved(), HttpResponse::HTTP_FORBIDDEN);

        /*
         * The window is enforced here, not in the browser. The button hiding
         * itself is a courtesy; this is the rule.
         */
        abort_unless($message->isWithinEditWindow(), HttpResponse::HTTP_FORBIDDEN);

        $message->forceFill([
            'body' => $request->validated('body'),
            'edited_at' => now(),
        ])->save();

        $this->announce->handle($message);

        return back();
    }

    /**
     * Take a message back.
     *
     * The row stays where it was and says so. A conversation is a record of
     * what passed between two parties, and a line that vanishes without trace
     * rewrites that record — so the words go, the fact of them does not.
     */
    public function remove(
        Request $request,
        Team $currentTeam,
        Conversation $conversation,
        Message $message,
    ): RedirectResponse {
        $this->authoriseOwnMessage($request->user(), $conversation, $message);

        if ($message->attachment_path !== null) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->forceFill([
            'body' => null,
            'attachment_path' => null,
            'removed_at' => now(),
        ])->save();

        $message->reactions()->delete();

        $this->announce->handle($message);

        return back();
    }

    /**
     * Add or take back a reaction.
     *
     * Pressing the same one twice removes it, which is what a reaction toggle
     * means everywhere else. Either participant may react — unlike editing,
     * reacting is a reply, not a rewrite.
     */
    public function react(
        ReactToMessageRequest $request,
        Team $currentTeam,
        Conversation $conversation,
        Message $message,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);
        abort_unless($message->conversation_id === $conversation->id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($message->isRemoved(), HttpResponse::HTTP_FORBIDDEN);

        $emoji = $request->validated('emoji');

        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            $this->announce->handle($message);

            return back();
        }

        $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        $this->announce->handle($message);

        return back();
    }

    /**
     * Guard the two actions only a message's own sender may take.
     */
    protected function authoriseOwnMessage(
        User $user,
        Conversation $conversation,
        Message $message,
    ): void {
        abort_unless($conversation->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

        // A message id from another thread must not resolve here.
        abort_unless($message->conversation_id === $conversation->id, HttpResponse::HTTP_NOT_FOUND);

        abort_unless($message->wasSentBy($user), HttpResponse::HTTP_FORBIDDEN);
    }

    /**
     * Name the other side of a thread from one participant's point of view.
     */
    protected function counterpartName(Conversation $conversation, User $user): string
    {
        if ($conversation->sideFor($user) === UserRole::Student) {
            return $conversation->project->team->clientProfile?->business_name
                ?? $conversation->project->team->name;
        }

        return $conversation->student->name;
    }
}
