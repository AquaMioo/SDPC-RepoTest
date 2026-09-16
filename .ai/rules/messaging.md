---
paths:
  - 'app/Actions/Messaging/**'
  - app/Http/Controllers/Messaging/ConversationController.php
  - app/Http/Controllers/Messaging/MeetingController.php
---

# Messaging

## A broadcast must never fail the message it announces
MessageSent is ShouldBroadcastNow, so it goes out on the request rather than through a worker. Reverb is a separate process, and when it is not listening the broadcast throws BroadcastException — after the message has already been committed. The sender got a 500 for a message that had in fact been sent, on every send, edit, remove and react.

So ConversationController never dispatches MessageSent directly. It goes through App\Actions\Messaging\AnnounceMessage, which catches Throwable and logs a warning. The live update is a courtesy on top of a write that already succeeded; the thread's 30-second usePoll backstop covers the gap. Same reasoning as SheerIdStudentVerifier — a service being down must never stop somebody using the platform.

config/broadcasting.php sets connect_timeout on the reverb connection for the same reason: without it a dead socket stalls the send for seconds before the catch is even reached.

`composer run dev` now starts reverb alongside serve, queue and vite. It did not before, which is why this was hit on every ordinary dev boot rather than being a rare production edge case.

There are tests pinning it — MessagingTest registers a deliberately unreachable broadcaster and asserts the message still sends.

## NotifyOfMessage fires once per wake, and its catch hides your bugs
A bell row per message makes the notification centre unreadable after any real exchange, so NotifyOfMessage only sends when the thread was fully read by the other side beforehand — a burst of ten lines is one notification. That answer has to be read BEFORE the message is written (afterwards every thread is unread), which is why ConversationController::send calls wasAlreadyUnread() at the top and passes the boolean down. Both people on the business side share one read marker, so the question is about the side, not the person.

The catch(Throwable) is the same posture as AnnounceMessage — a notification that cannot be written must not turn a delivered message into an error page. It also means a broken payload produces silence rather than a failure: the first version read $message->user (the relation is sender) and did nothing at all, and only the test caught it. Assert on the stored row, never on the absence of an exception.

## A call is running because somebody is in it, not because ended_at is null
Group threads put several people in one call, so a meeting's state comes from meeting_attendees: Meeting::inProgress() = started, not ended, and at least one attendee present (left_at null, last_seen_at within MeetingAttendee::PRESENCE_WINDOW). The call screen beats POST meetings.heartbeat every 20s and sends PATCH meetings.leave on Leave and on pagehide (keepalive fetch). Closed tabs never leave, so ended_at alone would offer dead calls forever.

store() with no scheduled_at JOINS the thread's in-progress meeting (200, no ring) under a lockForUpdate on the conversation. Do not go back to always creating a row: two people pressing Call split the group into two Agora channels. leave() ends the call only when the last person goes (Meeting::markLeft, a conditional update so it ends once) and then sends CallEnded. end() still closes the call for everyone, but the UI never calls it. Every remote track plays into its own RemoteTile; a shared container shows only the first person.
