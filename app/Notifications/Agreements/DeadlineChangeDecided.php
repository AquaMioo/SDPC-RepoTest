<?php

namespace App\Notifications\Agreements;

use App\Enums\DeadlineRequestStatus;
use App\Models\DeadlineChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student side when the client approves or declines a new date.
 *
 * Everyone on the build, not only whoever asked: the whole team works to the
 * deadline, so the whole team needs to know which date it is.
 */
class DeadlineChangeDecided extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public DeadlineChangeRequest $request) {}

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
        $mail = (new MailMessage)
            ->subject($this->approved()
                ? __('New deadline approved on :project', ['project' => $this->request->agreement->project->title])
                : __('Deadline change declined on :project', ['project' => $this->request->agreement->project->title]))
            ->line($this->approved()
                ? __('The client moved :what to :date.', ['what' => $this->subject(), 'date' => $this->request->proposed_on->format('j M Y')])
                : __('The client kept :what on :date.', ['what' => $this->subject(), 'date' => $this->request->previous_on?->format('j M Y') ?? __('its current date')]));

        return $this->request->decision_note === null ? $mail : $mail->line(__('Their note: :note', ['note' => $this->request->decision_note]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deadline.decided',
            'deadline_request_id' => $this->request->id,
            'agreement_id' => $this->request->agreement_id,
            'project_title' => $this->request->agreement->project->title,
            'status' => $this->request->status->value,
            'subject' => $this->subject(),
            'date' => ($this->approved() ? $this->request->proposed_on : $this->request->previous_on)?->toDateString(),
            'note' => $this->request->decision_note,
        ];
    }

    /**
     * Whether the client agreed to the new date.
     */
    protected function approved(): bool
    {
        return $this->request->status === DeadlineRequestStatus::Approved;
    }

    /**
     * What moved, or stayed, in words.
     */
    protected function subject(): string
    {
        return $this->request->isForFinalDeadline()
            ? __('the final deadline')
            : __('the deadline for ":task"', ['task' => $this->request->task?->title ?? __('a task')]);
    }
}
