<?php

namespace App\Actions\Messaging;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Messaging\NewMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raises a bell notification for the other side of a thread.
 *
 * The live broadcast only reaches somebody who has the thread open, and the
 * inbox badge only reaches somebody who looks at it. Anywhere else in the app
 * a message used to arrive in silence.
 *
 * Once per wake, not once per message. Whether the thread was already unread
 * for the recipient has to be read BEFORE the new message is written, which is
 * why this takes that decision as an argument rather than working it out for
 * itself — by the time the message exists, every thread is unread.
 *
 * Failure is swallowed for the same reason AnnounceMessage swallows a dead
 * broadcaster: the message is already committed, and a notification that
 * cannot be written must not turn a delivered message into an error page.
 */
class NotifyOfMessage
{
    /**
     * Notify whoever is on the other side, when the thread was quiet.
     *
     * @param  bool  $wasAlreadyUnread  Whether the recipients had unread
     *                                  messages here before this one landed.
     */
    public function handle(Message $message, bool $wasAlreadyUnread): void
    {
        if ($wasAlreadyUnread) {
            return;
        }

        try {
            $this->recipients($message->conversation, $message->sender)
                ->each(fn (User $recipient) => $recipient->notify(new NewMessage($message)));
        } catch (Throwable $exception) {
            Log::warning('A message was saved but the other side could not be notified.', [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Whether the other side already had something waiting here.
     *
     * Must be called BEFORE the new message is written — afterwards the answer
     * is always yes. Both people on the business side share one read marker,
     * so the question is about the side rather than about a person.
     */
    public function wasAlreadyUnread(Conversation $conversation, User $sender): bool
    {
        $recipient = $this->recipients($conversation, $sender)->first();

        return $recipient !== null && $conversation->isUnreadFor($recipient);
    }

    /**
     * Everyone on the opposite side of the thread from the sender.
     *
     * A thread is a student and a business, and the business is a team — so
     * writing to a client reaches whoever is on that team, the same set that
     * ApplicationReceived goes to.
     *
     * @return Collection<int, User>
     */
    protected function recipients(Conversation $conversation, User $sender): Collection
    {
        if ($conversation->sideFor($sender) === UserRole::Student) {
            return $conversation->project->team->members()->get();
        }

        return Collection::make([$conversation->student]);
    }
}
