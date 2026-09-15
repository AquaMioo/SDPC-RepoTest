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

## progress() is Granular Task Completion: verified tasks over all tasks
Since Project Management, Agreement::progress() counts agreement_tasks the client has **verified** over every task in every phase — not approved milestones any more. A task the student checked off (submitted) counts for nothing until the client verifies it, and no tasks is 0%, never a division by zero. SummariseProgress computes the same figure plus per-phase shares, current phase and next milestone; the page and both dashboards read it, so they cannot disagree. Every ring says "x of y tasks verified" beside the number.

The milestone status is derived from its tasks by SyncPhaseStatus (all verified → Approved, reopened when a task is added), so the calendar, contract screen and ledger keep working off status. Do not let anybody set a percentage or a task's Verified state except the client through AgreementTaskController::verify, and do not reintroduce a per-status percentage — the old 40%/80% scores were nobody's measurement.
