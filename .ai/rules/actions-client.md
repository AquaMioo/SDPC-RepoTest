---
paths:
  - app/Actions/Client/RespondToApplication.php
  - app/Actions/Client/UpdateClientProfile.php
---

# Actions Client

## A student builds one project at a time
User::holdsProjectInHand() is the predicate: an Accepted application on a project whose status ProjectStatus::isUnfinished(). It mirrors the client's one-posting cap in ProjectPolicy::create() and shares the same isUnfinished() definition.

Two doors into work, both guarded. ProjectBoardController::apply() blocks the student applying, and RespondToApplication::handle() blocks the client accepting — the second matters more, because acceptance is the moment work starts and a client could otherwise hire someone already busy. Shortlisting and inviting a busy student stay allowed on purpose; only acceptance is capped.

Screens with an apply affordance receive a `holdsProjectInHand` prop (student board, project detail) so the form is hidden with a reason rather than failing on submit.

## Never reset a client's verification_status — nothing can grant it back
A business is verified exactly once, in App\Actions\Fortify\CreateNewUser at registration. No admin screen, action or service can set VerificationStatus::Verified on a client profile afterwards — permit review was removed (see .ai/rules/admin.md).

So any code that moves a client to Pending is a one-way door. UpdateClientProfile used to do exactly that on permit upload: the client lost posting, hiring, inviting and testimonials via EnsureAccountIsVerified, and dropped off ClientDirectoryController's list, permanently. The profile screen was actively inviting it ("Upload your business permit for an administrator to review").

The permit upload is gone from the business profile screen and from UpdateClientProfileRequest. permit_path and stored files are left in place for whenever review returns. permit_path is also out of ClientProfile::COMPLETION_FIELDS — nothing can fill it, so it capped the meter below 100.

If permit review ever comes back, restore the admin queue FIRST, then the downgrade. There is a test: test_saving_the_profile_never_costs_a_client_their_verification.
