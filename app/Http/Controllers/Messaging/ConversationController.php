<?php

namespace App\Http\Controllers\Messaging;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
    /**
     * Show the inbox, with one thread open.
     */
    public function index(Request $request, Team $currentTeam, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        $threads = Conversation::query()
            ->forParticipant($user)
            ->with(['project.team.clientProfile', 'student', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $active = $conversation ?? $threads->first();

        if ($active !== null) {
            abort_unless($active->isParticipant($user), HttpResponse::HTTP_FORBIDDEN);

            $active->load(['messages.sender', 'project.team.clientProfile', 'student']);
            $active->markReadFor($user);
        }

        return Inertia::render('messaging/index', [
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
                'messages' => $active->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'author' => $message->sender?->name ?? 'Removed account',
                    'isMine' => $message->user_id === $user->id,
                    'at' => $message->created_at?->diffForHumans(short: true),
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Open — or reopen — the thread for a posting and a student.
     *
     * Called from the applicants screen on the client side and from a posting
     * on the student side, so neither has to know whether a thread exists yet.
     */
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

        DB::transaction(function () use ($conversation, $user, $request): void {
            $conversation->messages()->create([
                'user_id' => $user->id,
                'body' => $request->validated('body'),
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            /*
             * Sending counts as reading your own side, so a thread never comes
             * back unread to the person who just wrote in it.
             */
            $conversation->load('latestMessage');
            $conversation->markReadFor($user);
        });

        return back();
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
