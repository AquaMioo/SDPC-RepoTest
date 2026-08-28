---
paths:
  - 'app/Notifications/**'
---

# App Notifications

## Not every notification may be ShouldQueue, and database is not always a channel
Two traps in this directory.

Queueing: most notifications here are ShouldQueue, and there is no queue worker on Railway — 52 of them sat unsent in the jobs table before anyone noticed. Anything a person is waiting on must NOT be ShouldQueue. EmailOneTimePassword and Messaging\NewMessage are both deliberately unqueued and say so in their docblocks; a signup that looks broken, or a chat that only tells you about a message once a worker gets round to it, is worse than the row costing a millisecond inline.

Channels: an invitation is addressed to an email, not to an account. Teams\TeamInvitation goes out over Notification::route('mail', ...) when the invitee has not registered, and an AnonymousNotifiable has nowhere to store a database row — so its via() returns ['mail','database'] only for a real User. TeamInvitationController looks the address up and notifies the account when one exists. Tests pin both halves.
