<?php

namespace App\Actions\Notifications;

use App\Models\Team;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Turns a stored notification row into something a person can read.
 *
 * Notifications are written as a bare payload — a type string and whatever ids
 * the sender happened to have. Deciding what that means, and where it should
 * take you, happens once here rather than in the React layer, so the bell and
 * any later surface (an email digest, a mobile view) describe an event the
 * same way.
 *
 * An unrecognised type still renders. Rows outlive the code that wrote them,
 * and a notification centre that throws on a payload from three releases ago
 * is worse than one that says only what it is sure of.
 */
class PresentNotification
{
    /**
     * The account itself, for events no person triggered.
     */
    public const SYSTEM_SENDER = 'SDPC';

    /**
     * Describe one notification for the person it was sent to.
     *
     * @param  array<int, string|null>  $avatars  Actor id => picture, from avatarsFor().
     * @return array{id: string, from: string, initials: string, avatarUrl: string|null, title: string, body: string|null, url: string|null, at: string|null, sentOn: string|null, sentTime: string|null, read: bool}
     */
    public function handle(DatabaseNotification $notification, Team $team, array $avatars = []): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        [$title, $body, $url] = $this->describe($data, $team);

        $from = $this->actor($data) ?? self::SYSTEM_SENDER;
        $actorId = $this->actorId($data);

        return [
            'id' => $notification->id,
            'from' => $from,
            'initials' => $this->initials($from),
            /*
             * Null for a row whose payload names nobody, and for every row
             * written before the actor's id was stored — those keep their
             * initials, which is all their payload can support.
             */
            'avatarUrl' => $actorId === null ? null : ($avatars[$actorId] ?? null),
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'at' => $notification->created_at?->diffForHumans(short: true),
            /*
             * Split rather than one formatted string: the list stacks the date
             * over the time in its own column, and the bell menu joins them
             * back with a comma. Formatting it once here keeps the two
             * surfaces from drifting into different date styles.
             */
            'sentOn' => $notification->created_at?->format('M j'),
            'sentTime' => $notification->created_at?->format('g:i a'),
            'read' => $notification->read_at !== null,
        ];
    }

    /**
     * Who the notification is from, when a person triggered it.
     *
     * Read by probing the name keys the payloads actually carry rather than by
     * matching on type a second time. Two reasons: the type match below would
     * have to be kept in step with this one, and a payload from a release this
     * code no longer knows about still yields a sender if it happens to carry
     * one of these keys. Events nobody triggered — an approval, a status
     * change, a countersignature — have no name in them at all and fall back
     * to the system sender.
     *
     * @param  array<string, mixed>  $data
     */
    protected function actor(array $data): ?string
    {
        foreach (['sender_name', 'student_name', 'client_name', 'inviter_name', 'caller_name'] as $key) {
            $name = $this->text($data, $key);

            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Which account triggered it, when the payload says.
     *
     * Probed the same way as actor() above, and for the same reasons. The id
     * keys were added alongside the names later, so a row written before that
     * yields null here and falls back to initials — which every row can do.
     *
     * @param  array<string, mixed>  $data
     */
    protected function actorId(array $data): ?int
    {
        foreach (['sender_id', 'student_id', 'inviter_id', 'caller_id'] as $key) {
            $id = $data[$key] ?? null;

            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Every actor's picture for a page of notifications, in one query.
     *
     * Resolved here rather than per row: the bell draws several notifications
     * at once, and asking for a user inside handle() would be a query each.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<int, string|null>
     */
    public function avatarsFor(Collection $notifications): array
    {
        $ids = $notifications
            ->map(fn (DatabaseNotification $row): ?int => $this->actorId((array) $row->data))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'avatar', 'avatar_path'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->avatarUrl()])
            ->all();
    }

    /**
     * The one or two letters drawn in the avatar circle.
     *
     * What a row falls back to when there is no picture: the actor has none,
     * or the payload predates actorId() and names nobody to look up. Derived
     * from the name itself, which every row ever written carries.
     */
    protected function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $letters);
    }

    /**
     * Work out the headline, the supporting line and where it leads.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    protected function describe(array $data, Team $team): array
    {
        $project = $this->text($data, 'project_title');
        $slug = $this->text($data, 'project_slug');
        $reference = $this->text($data, 'agreement_reference');

        return match ($this->text($data, 'type')) {
            'application.received' => [
                __(':student applied to :project', [
                    'student' => $this->text($data, 'student_name') ?? __('A student'),
                    'project' => $project ?? __('your posting'),
                ]),
                __('Review the applicants and decide who to take on.'),
                $slug === null ? null : route('projects.applicants.index', [
                    'current_team' => $team->slug,
                    'project' => $slug,
                ]),
            ],
            'project.published' => [
                __(':project is open to students', ['project' => $project ?? __('Your posting')]),
                __('An administrator approved it, so it is on the student board now.'),
                $slug === null ? null : route('projects.show', [
                    'current_team' => $team->slug,
                    'project' => $slug,
                ]),
            ],
            'project.status_changed' => [
                __(':project changed status', ['project' => $project ?? __('Your posting')]),
                $this->text($data, 'summary'),
                $slug === null ? null : route('projects.show', [
                    'current_team' => $team->slug,
                    'project' => $slug,
                ]),
            ],
            'message.received' => [
                __(':sender messaged you', [
                    'sender' => $this->text($data, 'sender_name') ?? __('Somebody'),
                ]),
                $this->text($data, 'preview'),
                $this->conversationUrl($data, $team),
            ],
            'project.invitation' => [
                __(':client invited you to :project', [
                    'client' => $this->text($data, 'client_name') ?? __('A client'),
                    'project' => $project ?? __('a project'),
                ]),
                __('Look at the brief and reply from your workflow.'),
                route('student.workflow', ['current_team' => $team->slug]),
            ],
            'team.invitation' => [
                __(':inviter invited you to join :team', [
                    'inviter' => $this->text($data, 'inviter_name') ?? __('Somebody'),
                    'team' => $this->text($data, 'team_name') ?? __('a team'),
                ]),
                __('Accept or decline it from your dashboard.'),
                null,
            ],
            'invitation.accepted' => [
                __(':student accepted your invitation', [
                    'student' => $this->text($data, 'student_name') ?? __('A student'),
                ]),
                __('An agreement has been drafted. The work starts once both sides have signed it.'),
                route('agreements.index', ['current_team' => $team->slug]),
            ],
            'invitation.closed' => [
                __(':student is no longer available', [
                    'student' => $this->text($data, 'student_name') ?? __('A student'),
                ]),
                __(':reason Your invitation to :project was closed.', [
                    'reason' => ($data['accepted_invitation'] ?? true) === false
                        ? __('They have already been taken on by another client.')
                        : __('They have already accepted an invitation from another client.'),
                    'project' => $project ?? __('your posting'),
                ]),
                route('recruit.index', ['current_team' => $team->slug]),
            ],
            'team.invitation_closed' => [
                __(':student joined another team', [
                    'student' => $this->text($data, 'student_name') ?? __('A student'),
                ]),
                __('They accepted an invitation to :joined, so your invitation to :team was cancelled.', [
                    'joined' => $this->text($data, 'joined_team_name') ?? __('another team'),
                    'team' => $this->text($data, 'team_name') ?? __('your team'),
                ]),
                $this->text($data, 'team_slug') === null
                    ? null
                    : route('teams.edit', ['team' => $this->text($data, 'team_slug')]),
            ],
            'application.accepted' => [
                __('You were accepted for :project', ['project' => $project ?? __('a project')]),
                __('An agreement has been drafted. The work starts once both sides have signed it.'),
                route('student.workflow', ['current_team' => $team->slug]),
            ],
            'agreement.signed' => [
                __('Agreement :reference was signed', ['reference' => $reference ?? '']),
                __('Once both parties have signed, the project moves into progress.'),
                $this->agreementUrl($data, $team),
            ],
            'deadline.requested' => [
                __(':student asked to move :what', [
                    'student' => $this->text($data, 'student_name') ?? __('The student'),
                    'what' => $this->text($data, 'subject') ?? __('a deadline'),
                ]),
                __('On :project. The date stays as it is until you approve or decline it.', ['project' => $project ?? __('your project')]),
                $this->projectManagementUrl($data, $team),
            ],
            'deadline.decided' => [
                $this->text($data, 'status') === 'approved'
                    ? __('The client approved moving :what', ['what' => $this->text($data, 'subject') ?? __('a deadline')])
                    : __('The client kept :what', ['what' => $this->text($data, 'subject') ?? __('a deadline')]),
                $this->text($data, 'note') ?? __('On :project.', ['project' => $project ?? __('your project')]),
                $this->projectManagementUrl($data, $team),
            ],
            'project.completed' => [
                __(':project is complete', ['project' => $project ?? __('Your project')]),
                __(':business accepted the turnover. You are free to take on your next project.', [
                    'business' => $this->text($data, 'business_name') ?? __('The client'),
                ]),
                $this->projectManagementUrl($data, $team),
            ],
            'agreement.changes_requested' => [
                __('Changes were requested on :reference', ['reference' => $reference ?? '']),
                $this->text($data, 'note'),
                $this->agreementUrl($data, $team),
            ],
            'conversation.member_added' => $this->text($data, 'audience') === 'member'
                ? [
                    __(':inviter added you to a group chat', [
                        'inviter' => $this->text($data, 'inviter_name') ?? __('Your team creator'),
                    ]),
                    __('About :project with :business. You can read and reply in the thread now.', [
                        'project' => $project ?? __('a project'),
                        'business' => $this->text($data, 'business_name') ?? __('the client'),
                    ]),
                    $this->conversationUrl($data, $team),
                ]
                : [
                    __(':member joined your conversation', [
                        'member' => $this->text($data, 'member_name') ?? __('A student'),
                    ]),
                    __(':inviter from :team added them to the chat about :project.', [
                        'inviter' => $this->text($data, 'inviter_name') ?? __('The team creator'),
                        'team' => $this->text($data, 'team_name') ?? __('the student team'),
                        'project' => $project ?? __('your posting'),
                    ]),
                    $this->conversationUrl($data, $team),
                ],
            /* Written before group chats became invite-only; kept so old rows still read. */
            'conversation.team_joined' => $this->text($data, 'audience') === 'team'
                ? [
                    __(':student added your team to a conversation', [
                        'student' => $this->text($data, 'student_name') ?? __('Your teammate'),
                    ]),
                    __('About :project with :business. You can read and reply in the thread now.', [
                        'project' => $project ?? __('a project'),
                        'business' => $this->text($data, 'business_name') ?? __('the client'),
                    ]),
                    $this->conversationUrl($data, $team),
                ]
                : [
                    __(':team joined your conversation with :student', [
                        'team' => $this->text($data, 'team_name') ?? __('A student team'),
                        'student' => $this->text($data, 'student_name') ?? __('the student'),
                    ]),
                    __('About :project. Everyone on the team can read and write the thread now.', [
                        'project' => $project ?? __('your posting'),
                    ]),
                    $this->conversationUrl($data, $team),
                ],
            'call.incoming' => [
                __(':caller called you', ['caller' => $this->text($data, 'caller_name') ?? __('Somebody')]),
                __('A video call about :project.', ['project' => $project ?? __('your project')]),
                $this->conversationUrl($data, $team),
            ],
            'account.access_blocked' => [
                __('Someone tried to sign in to your account'),
                __(':device was blocked because you were using the account. If it was not you, change your password.', [
                    'device' => implode(' ', array_filter([
                        $this->text($data, 'device') ?? __('A device'),
                        $this->text($data, 'ip_address') === null ? null : '('.$this->text($data, 'ip_address').')',
                    ])),
                ]),
                route('security.edit'),
            ],
            default => [__('Something happened on your account'), null, null],
        };
    }

    /**
     * Build the link to a thread, when the payload names one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function conversationUrl(array $data, Team $team): ?string
    {
        $id = $data['conversation_id'] ?? null;

        return is_int($id) || is_string($id)
            ? route('messages.show', ['current_team' => $team->slug, 'conversation' => $id])
            : null;
    }

    /**
     * Build the link to a build's Project Management, on the agreement the
     * payload names when it names one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function projectManagementUrl(array $data, Team $team): string
    {
        $id = $data['agreement_id'] ?? null;

        return route('project-management', [
            'current_team' => $team->slug,
            ...(is_int($id) || is_string($id) ? ['agreement' => $id] : []),
        ]);
    }

    /**
     * Build the link to an agreement, when the payload names one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function agreementUrl(array $data, Team $team): ?string
    {
        $id = $data['agreement_id'] ?? null;

        return is_int($id) || is_string($id)
            ? route('agreements.show', ['current_team' => $team->slug, 'agreement' => $id])
            : null;
    }

    /**
     * Read one string out of a payload that is only ever trusted to be an array.
     *
     * @param  array<string, mixed>  $data
     */
    protected function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
