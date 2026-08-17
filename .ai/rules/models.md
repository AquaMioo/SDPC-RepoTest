---
paths:
  - app/Models/Conversation.php
---

# Models

## Track read state by message id, never by timestamp
Laravel stores datetimes to the second, so a reply landing in the same second as the other side's last read compares equal and reads as "already seen" — the thread silently shows no unread. Conversation keeps client_read_message_id / student_read_message_id instead, and isUnreadFor() compares ids plus checks the sender. Do not "simplify" these back to timestamps; there is a frozen-clock test pinning the exact failure.
