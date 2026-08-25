---
paths:
  - app/Models/Conversation.php
  - app/Models/Agreement.php
---

# Models

## Track read state by message id, never by timestamp
Laravel stores datetimes to the second, so a reply landing in the same second as the other side's last read compares equal and reads as "already seen" — the thread silently shows no unread. Conversation keeps client_read_message_id / student_read_message_id instead, and isUnreadFor() compares ids plus checks the sender. Do not "simplify" these back to timestamps; there is a frozen-clock test pinning the exact failure.

## agreements is unique on (application_id, version), not application_id
One application can hold several agreement rows — one per negotiated version. Application::agreement() is a HasOne with latestOfMany('version'); Application::agreements() returns every version.

Do not "tidy" this into a unique index on application_id alone. The whole change-request design depends on old versions surviving alongside the current one: the superseded row keeps the signatures that were actually given against the terms that were actually on the table.

progress() reports the share of milestones the client has **approved**, weighting every milestone equally rather than by price — a cheap turnover phase is not less done than an expensive one.

Approval is the only thing it counts, and that is deliberate. MilestoneStatus used to carry a progress() that scored "in progress" at 40% and "submitted" at 80%; those numbers were nobody's, and averaging them produced a ring that looked measured while resting on a status somebody had merely set. A milestone now shows its status, and the ring says "1 of 3 approved" beside the figure so the percentage cannot be read as "how much of the work is done". Do not reintroduce a per-status percentage.
