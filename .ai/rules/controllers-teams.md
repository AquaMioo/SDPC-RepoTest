---
paths:
  - 'app/Http/Controllers/Teams/**'
---

# Controllers Teams

## A team invitation needs a real student account, and each role has one holder
CreateTeamInvitationRequest validates the address with App\Rules\RegisteredStudent: it must belong to an existing account whose role is Student (matched case-insensitively). Invitations used to go out to any address — a lead who mistyped one was told "Invitation sent" and waited on somebody who was never coming (testers, 2026-09-23). TeamInvitationController::store therefore always has an invitee to notify; the AnonymousNotifiable arm of Teams\TeamInvitation::via() is kept for old rows and direct sends, not for this path.

App\Rules\UnclaimedTeamRole guards both doors that hand a job title out — inviting (CreateTeamInvitationRequest) and re-assigning (UpdateTeamMemberRequest, which passes the member's id so their own title is not a clash). It reads Team::takenRoles(), which counts members plus invitations that are still pending, because a promised seat carries its role. Teams that already hold a duplicate from before this rule are left as they are. The edit screen gets `takenRoles` and greys those options out in both the invite dialog and the member dropdown.

In tests, invite a real `User::factory()->student()` and give each invitation a different role, or the request refuses it.
