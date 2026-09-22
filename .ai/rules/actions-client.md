---
paths:
  - app/Actions/Client/RespondToApplication.php
  - app/Actions/Client/UpdateClientProfile.php
---

# Actions Client

## A student builds one project at a time — and so do their teammates
User::isLockedToProject() is the predicate every gate asks (scopeLockedToProject is the same thing as a subquery, for lists). A student is locked when they hold a build themselves — User::holdsProjectInHand(): an Accepted application on a project whose status ProjectStatus::isUnfinished() — OR they are on a team whose owner holds one. It mirrors the client's one-posting cap in ProjectPolicy::create() and shares the same isUnfinished() definition, so CompleteProject releases everyone just by moving the project to Completed.

Do not go back to holdsProjectInHand() at a gate: it only sees the signer, and a teammate could apply elsewhere mid-build. It stays for the narrower questions — "is this the signer?" (User::projectHolder) and Team::isBuilding(). The props are still named `holdsProjectInHand` (board, project detail) and `isTaken` (client's student profile) but are fed by isLockedToProject(). Recruit leaves locked students out entirely.

Two doors into work, both guarded. ProjectBoardController::apply() blocks the student applying, and RespondToApplication::handle() blocks the client accepting — the second matters more, because acceptance is the moment work starts and a client could otherwise hire someone already busy. RespondToInvitation::accept() blocks the student accepting a second invitation.

Once a student is taken on by either route, App\Actions\Student\CloseOtherInvitations closes (Withdrawn) every other invitation still open for them, and emails plus bell-notifies each business that sent one (Client\InvitationClosed, which is deliberately not queued). Inviting a student who is already taken is refused in InviteStudentRequest, and the student profile screen says so (`isTaken`). Testers asked for this: an invited student can accept only one, and the other inviters must be told. Shortlisting a busy applicant is still allowed.

Screens with an apply affordance receive that `holdsProjectInHand` prop so the form is hidden with a reason rather than failing on submit.

## Never reset a client's verification_status — nothing can grant it back
A business is verified exactly once, in App\Actions\Fortify\CreateNewUser at registration. No admin screen, action or service can set VerificationStatus::Verified on a client profile afterwards — permit review was removed (see .ai/rules/admin.md).

So any code that moves a client to Pending is a one-way door. UpdateClientProfile used to do exactly that on permit upload: the client lost posting, hiring, inviting and testimonials via EnsureAccountIsVerified, and dropped off ClientDirectoryController's list, permanently. The profile screen was actively inviting it ("Upload your business permit for an administrator to review").

The permit upload is gone from the business profile screen and from UpdateClientProfileRequest. permit_path and stored files are left in place for whenever review returns. permit_path is also out of ClientProfile::COMPLETION_FIELDS — nothing can fill it, so it capped the meter below 100.

If permit review ever comes back, restore the admin queue FIRST, then the downgrade. There is a test: test_saving_the_profile_never_costs_a_client_their_verification.
