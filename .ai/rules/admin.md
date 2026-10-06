---
paths:
  - app/Http/Controllers/Admin/AdminPostingController.php
---

# Admin

## Admin approval is the only thing that opens a posting
A client can only save a project as draft or pending_review (SaveProjectRequest), and the student board lists status=open only. AdminPostingController::update is the single place that sets Open, so if postings are "invisible to students" check this queue before suspecting the board query. Approval also backfills published_at and fires ProjectStatusChanged.

## Postings live on the dashboard overview; Content Management is its own tab
The scope names five things in the developers module: Login, Dashboard Overview, User Account Management, Content Management, Report and Issues Management. Postings was never one of them, so `GET admin/postings` is gone and the queue renders inside admin/dashboard (built by App\Support\AdminPostingQueue, beside AdminStatistics). A test pins that /admin/postings 404s.

Content Management is a tab of its own again since 2026-10-07 (owner): GET admin/content (AdminContentController::index → admin/content), saved by the unchanged AdminContentController::update. The dashboard no longer carries the `content` prop.

The queue's buttons are Approve / Reopen and Remove (owner, 2026-10-07; Remove replaced Close). Remove is DELETE admin.postings.destroy: a dialog asks the administrator why, the reason is required, App\Notifications\Client\PostingRemoved (type project.removed, carrying the title and reason as text because the posting is gone) goes to the team, then the posting is soft-deleted. Only postings ProjectStatus::isModeratable() may be removed, like every other queue decision. PATCH admin.postings.update still accepts 'closed' (the issue screen's Close posting sets the same status).

The admin console has no link from the landing page (owner, 2026-10-07): it is reached only by typing /admin/login.

## Monitoring is the appeal queue too
Off the admin nav since 2026-10-07 (owner), but the screen and its routes stay: appeals are decided only there, and the activity bell's appeal rows (AdminActivityFeed) still link to admin.monitoring.

AdminMonitoringController lists monitored *and* deactivated accounts. Deactivated ones are there because they cannot sign in to appeal from their settings, so the guest page at /appeal is their only door and this is where what they wrote arrives. Granting restores the account to Approved; denying leaves the decision standing and requires a reason, because the account is shown it.

## User management is one toggle plus Delete; the bell is built from records
Since 2026-10-01 admin/users has no Status column and no Pending/Approve/Monitor buttons: one Deactivate/Reactivate toggle (PATCH admin.users.status.update with deactivated/approved) and a Delete behind "Are you sure that you want to delete this user account?". Delete is AdminUserController::destroy → App\Actions\Admin\DeleteUserAccount: force-deletes any team the account owns that nobody else is on (a client's business takes its postings and profile with it), deletes the user (DB cascades do the rest) and the uploaded avatar. It refuses the admin's own account and other admins. Monitoring accounts still happens from the Issues screen.

Both lists page in the browser with components/sdpc/page-numbers.tsx (5 postings on the dashboard, 15 users), because the controllers send them whole; the role filter (All/Client/Student) sits beside the search. No request per page.

The admin bell (components/admin/activity-bell.tsx) reads the shared `adminActivity` prop, sent only to admins: App\Support\AdminActivityFeed merges the latest sign-ups, reports (Issue), appeals and testimonials. "Seen" is a cache timestamp per admin set by POST admin.activity.seen — no notification rows, so nothing has to remember to notify the admin.
