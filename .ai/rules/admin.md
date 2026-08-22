---
paths:
  - app/Http/Controllers/Admin/AdminPostingController.php
---

# Admin

## Admin approval is the only thing that opens a posting
A client can only save a project as draft or pending_review (SaveProjectRequest), and the student board lists status=open only. AdminPostingController::update is the single place that sets Open, so if postings are "invisible to students" check this queue before suspecting the board query. Approval also backfills published_at and fires ProjectStatusChanged.

## Postings and Content live on the dashboard overview, not on screens of their own
The scope names five things in the developers module: Login, Dashboard Overview, User Account Management, Content Management, Report and Issues Management. Postings was never one of them, so `GET admin/postings` and `GET admin/content` are gone and both render inside admin/dashboard.

Their write endpoints stayed exactly where they were — AdminPostingController::update and AdminContentController::update — so approving a posting still means one thing. The queue itself is built by App\Support\AdminPostingQueue, beside AdminStatistics.

Do not re-add the standalone screens. A test pins that /admin/postings 404s.

## Monitoring is the appeal queue too
AdminMonitoringController lists monitored *and* deactivated accounts. Deactivated ones are there because they cannot sign in to appeal from their settings, so the guest page at /appeal is their only door and this is where what they wrote arrives. Granting restores the account to Approved; denying leaves the decision standing and requires a reason, because the account is shown it.
