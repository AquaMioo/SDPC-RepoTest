<?php

namespace App\Notifications\Agreements;

use App\Models\DeadlineChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the business when the student side asks to move a deadline.
 *
 * Nothing moves until the client answers, so an ask nobody sees is a build
 * quietly running against a date it cannot make.
 */
class DeadlineChangeRequested extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('A deadline change on :project needs your decision', ['project' => $this->projectTitle()]))
            ->line(__(':student asked to move :what from :from to :to.', [
                'student' => $this->request->requester?->name ?? __('The student'),
                'what' => $this->subject(),
                'from' => $this->request->previous_on?->format('j M Y') ?? __('no date'),
                'to' => $this->request->proposed_on->format('j M Y'),
            ]))
            ->line(__('Approve or decline it in Project Management. The date stays as it is until you do.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deadline.requested',
            'deadline_request_id' => $this->request->id,
            'agreement_id' => $this->request->agreement_id,
            'project_title' => $this->projectTitle(),
            'student_id' => $this->request->requested_by,
            'student_name' => $this->request->requester?->name,
            'subject' => $this->subject(),
            'proposed_on' => $this->request->proposed_on->toDateString(),
        ];
    }

    /**
     * What would move, in words: a task by its title, or the final deadline.
     */
    protected function subject(): string
    {
        return $this->request->isForFinalDeadline()
            ? __('the final deadline')
            : __('the deadline for ":task"', ['task' => $this->request->task?->title ?? __('a task')]);
    }

    /**
     * The posting the build is for.
     */
    protected function projectTitle(): string
    {
        return $this->request->agreement->project->title;
    }
}
