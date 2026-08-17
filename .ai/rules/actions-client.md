---
paths:
  - app/Actions/Client/RespondToApplication.php
---

# Actions Client

## A student builds one project at a time
User::holdsProjectInHand() is the predicate: an Accepted application on a project whose status ProjectStatus::isUnfinished(). It mirrors the client's one-posting cap in ProjectPolicy::create() and shares the same isUnfinished() definition.

Two doors into work, both guarded. ProjectBoardController::apply() blocks the student applying, and RespondToApplication::handle() blocks the client accepting — the second matters more, because acceptance is the moment work starts and a client could otherwise hire someone already busy. Shortlisting and inviting a busy student stay allowed on purpose; only acceptance is capped.

Screens with an apply affordance receive a `holdsProjectInHand` prop (student board, project detail) so the form is hidden with a reason rather than failing on submit.
