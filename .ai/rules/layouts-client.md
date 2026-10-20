---
paths:
  - 'resources/js/layouts/client/**'
---

# Layouts Client

## Nav order, no Posting item, and walkthrough anchors
There is NO Posting nav item (owner, 2026-10-20, reversing the 2026-10-18 one): the client nav is Dashboard, Recruit, Project Management, Agreement, Team; the student nav is Dashboard, Get Client (student.board.index, the postings board), Project Management, Agreement, Team. The student client directory (student.clients.index) stays but is not in the nav. The walkthrough's "Posting" step points at the client dashboard's Post a Job button (data-tour="post-a-job", on both the enabled link and the disabled button) and at the student's Get Client link. Every nav link carries data-tour="nav-<label-slug>" and the four header icons sit in spans with data-tour="icon-messages|icon-notifications|icon-profile|icon-settings": the first-login walkthrough (components/onboarding-tour.tsx, custom, no package) points at those, so keep them when touching the header. A step whose anchor is not on the page is passed over. Nav links light up on an exact URL match, except items marked nested (Agreement): AgreementController::index redirects to /agreements/{id} while an agreement is standing, so Agreement is also lit on any page under /agreements (owner, 2026-10-20). The tour shows while users.has_completed_onboarding is false (default false, also in User::$attributes) and POST onboarding.complete sets it on finish, skip or Escape.
