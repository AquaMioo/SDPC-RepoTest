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

progress() averages milestone statuses in equal steps rather than weighting by price — a cheap turnover phase is not less done than an expensive one.
