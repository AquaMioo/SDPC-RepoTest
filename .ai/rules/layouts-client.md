---
paths:
  - 'resources/js/layouts/client/**'
---

# Layouts Client

## Nav order, Posting item, and walkthrough anchors
Since 2026-10-18 (owner) the client nav is Dashboard, Posting (projects.index), Recruit, Project Management, Agreement, Team; the student nav is Dashboard, Posting (student.board.index, the postings board), Get Client (student.clients.index, the business directory), Project Management, Agreement, Team. Every nav link carries data-tour="nav-<label-slug>" and the four header icons sit in spans with data-tour="icon-messages|icon-notifications|icon-profile|icon-settings": the first-login walkthrough (components/onboarding-tour.tsx, custom, no package) points at those, so keep them when touching the header. The tour shows while users.has_completed_onboarding is false (default false, also in User::$attributes) and POST onboarding.complete sets it on finish, skip or Escape.
