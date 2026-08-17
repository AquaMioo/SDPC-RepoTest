---
paths:
  - app/Http/Controllers/Admin/AdminPostingController.php
---

# Admin

## Admin approval is the only thing that opens a posting
A client can only save a project as draft or pending_review (SaveProjectRequest), and the student board lists status=open only. AdminPostingController::update is the single place that sets Open, so if postings are "invisible to students" check this queue before suspecting the board query. Approval also backfills published_at and fires ProjectStatusChanged.
