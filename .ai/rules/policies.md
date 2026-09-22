---
paths:
  - app/Policies/ProjectPolicy.php
  - app/Policies/AgreementPolicy.php
---

# Policies

## A client team may only have one unfinished posting
ProjectPolicy::create() denies a second posting while the team holds one whose status ProjectStatus::isUnfinished() — draft, pending review, open, in progress. Completed, closed and archived free the slot.

Drafts count on purpose: otherwise the draft drawer is a way around the cap. `duplicate` delegates to `create`, so a copy cannot slip past it either — duplicating only works once the source is finished.

Anything that mints a posting must route through this policy. Screens that link to projects.create pass a `canCreate` / `canPostProject` prop (client board, client dashboard) and hide the button, because the policy answers with a bare 403.

## Teammates read the signed contract, and appear on both Project team panels
AgreementPolicy::view now passes for the signing student's teammates when the agreement is Active or Completed — they build what it describes, so they read the scope, dates and terms. Drafts (Draft / AwaitingSignatures) stay between the two people whose names go on it, and update/sign/requestChanges each ask partyFor() separately, so widening view() grants nothing else. AgreementController::visibleTo mirrors it for the list, and the row's `counterparty` is decided by which side the reader sits on (belongsToTeam), not by whether they signed. PresentAgreement sends party null for them; signature-form.tsx says so rather than offering to record a signature.

Both "Project team" panels read the same group: ClientDashboardController::projectTeam and BuildStudentDashboard both add User::teammates() — the members of the team the accepted student owns, each carrying their membership in `pivot` for the job title — to the accepted applicants. Before this an invited member was invisible on both dashboards (testers, 2026-09-23).
