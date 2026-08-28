<?php

namespace App\Actions\Notifications;

use App\Models\Team;
use Illuminate\Notifications\DatabaseNotification;

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
     * Describe one notification for the person it was sent to.
     *
     * @return array{id: string, title: string, body: string|null, url: string|null, at: string|null, read: bool}
     */
    public function handle(DatabaseNotification $notification, Team $team): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        [$title, $body, $url] = $this->describe($data, $team);

        return [
            'id' => $notification->id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'at' => $notification->created_at?->diffForHumans(short: true),
            'read' => $notification->read_at !== null,
        ];
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
            'agreement.changes_requested' => [
                __('Changes were requested on :reference', ['reference' => $reference ?? '']),
                $this->text($data, 'note'),
                $this->agreementUrl($data, $team),
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
