---
paths:
  - app/Policies/ProjectPolicy.php
---

# Policies

## A client team may only have one unfinished posting
ProjectPolicy::create() denies a second posting while the team holds one whose status ProjectStatus::isUnfinished() — draft, pending review, open, in progress. Completed, closed and archived free the slot.

Drafts count on purpose: otherwise the draft drawer is a way around the cap. `duplicate` delegates to `create`, so a copy cannot slip past it either — duplicating only works once the source is finished.

Anything that mints a posting must route through this policy. Screens that link to projects.create pass a `canCreate` / `canPostProject` prop (client board, client dashboard) and hide the button, because the policy answers with a bare 403.
