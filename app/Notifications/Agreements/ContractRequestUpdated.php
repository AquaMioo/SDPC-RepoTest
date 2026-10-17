<?php

namespace App\Notifications\Agreements;

use App\Enums\ContractRequestType;
use App\Models\Addendum;
use App\Models\ContractRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A denial or a termination request moved: asked (to the side that answers),
 * accepted or declined (to the side that asked), or withdrawn.
 */
class ContractRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  'requested'|'accepted'|'declined'|'withdrawn'  $event
     */
    public function __construct(
        public ContractRequest $request,
        public string $event,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject(self::headline($payload))
            ->line(__('Reason given: :reason', ['reason' => $this->request->reason]))
            ->line(__('Open SDPC to see it and answer.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $subject = $this->request->subject;
        $agreement = $this->request->agreement();

        return [
            'type' => 'contract.request',
            'event' => $this->event,
            'request_type' => $this->request->type->value,
            'party' => $this->request->party->value,
            'subject' => $subject instanceof Addendum ? 'addendum' : 'agreement',
            'agreement_id' => $agreement->id,
            'agreement_reference' => $agreement->reference,
            'addendum_id' => $subject instanceof Addendum ? $subject->id : null,
            'project_id' => $agreement->project_id,
            'project_title' => $agreement->project->title,
            'reason' => Str::limit($this->request->reason, 300),
        ];
    }

    /**
     * The one-line summary both the email and the notification centre use.
     *
     * @param  array<string, mixed>  $data
     */
    public static function headline(array $data): string
    {
        $what = ($data['request_type'] ?? null) === ContractRequestType::Termination->value
            ? __('ending the collaboration')
            : (($data['subject'] ?? null) === 'addendum' ? __('denying the addendum') : __('denying the agreement'));
        $project = is_string($data['project_title'] ?? null) ? $data['project_title'] : __('your project');
        $side = ($data['party'] ?? null) === 'client' ? __('The client') : __('The student');

        return match ($data['event'] ?? null) {
            'requested' => __(':side asked about :what for :project', ['side' => $side, 'what' => $what, 'project' => $project]),
            'accepted' => __('Your request about :what for :project was accepted', ['what' => $what, 'project' => $project]),
            'declined' => __('Your request about :what for :project was declined', ['what' => $what, 'project' => $project]),
            default => __(':side withdrew the request about :what for :project', ['side' => $side, 'what' => $what, 'project' => $project]),
        };
    }
}
