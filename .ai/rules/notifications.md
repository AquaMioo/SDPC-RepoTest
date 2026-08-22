---
paths:
  - 'app/Actions/Notifications/**'
---

# Notifications

## An unrecognised notification payload must still render
Notification rows outlive the code that wrote them. PresentNotification::describe() matches on the stored `type` string and has a `default` arm returning a plain line with no URL, and every field is read through text() rather than accessed directly — the payload is only ever trusted to be an array.

Do not "tidy" the default arm away or reach into $data['x'] directly. A renamed type from two releases ago must degrade to a readable line, not take the whole notification centre down with an undefined-key error. There is a test pinning this: test_a_payload_the_code_no_longer_recognises_still_renders.
