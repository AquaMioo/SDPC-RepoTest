---
paths:
  - 'app/Actions/Teams/**'
---

# Teams

## One team per student: JoinTeam replaces a solo team, GiveOwnTeam backfills
A student is on exactly one team. JoinTeam (used by TeamInvitationController::accept) dissolves the student's own team when nobody else is on it (memberships, its sent invitations, removal votes, conversations.student_team_id → null, soft delete), and refuses when they lead a non-solo team or already sit on someone else's. JoinTeam::refusal() is also asked at send time in TeamInvitationController::store; only students can be invited.

Because the own team is gone after joining, leaving (TeamController::leave) or being voted out (VoteToRemoveMember) calls GiveOwnTeam, which creates a fresh personal team. Do not go back to $user->personalTeam() for that — every signup team is is_personal, so for a joined member it returns the team they just left. TeamPolicy::leave is therefore "member and not owner", no longer "not is_personal". After accepting, redirect with an explicit current_team: the URL defaults still point at the dissolved team.

## A team on a build stays together until the client completes it
Team::isBuilding() is true while the team's owner holds a project (User::holdsProjectInHand). While it is, TeamPolicy::leave refuses (leaving would free the student to apply elsewhere and leave the client a developer short), and JoinTeam::refusal() refuses any student who isLockedToProject() unless they are already on the team being joined. The Teams page gets `buildingTeamIds` and hides Leave for those teams instead of offering a button the server refuses — keep it a list (`->values()->modelKeys()`), or a filtered collection serialises as a JSON object. Completing the project (CompleteProject) is what releases them; nothing else needs undoing.

## A group chat is at most five people: a team of four plus the client
Team::MAX_MEMBERS = 4 counts the leader, and only students can be invited to a team (JoinTeam::refusal), so a client's team is only the client. Conversation::participants() is the thread's student, plus the teammates the creator invited into the chat (conversation_members rows, counted only while the person is still on the thread's team), plus the client: 5 at most. So leaving (TeamController::leave) and being voted off (VoteToRemoveMember::remove) both call Conversation::releaseFromTeam(). It drops student_team_id and the member rows on the leaver's own threads, and deletes the leaver's rows in the team's other threads. Without it, the chat holds the leaver plus a refilled team of four, which is six. Migration 2026_09_16_133431 fixed the threads left that way before the release existed. The call grid (video-call.tsx gridColumns) is sized for at most four other tiles because of this.

## Joining a team cancels the student's other team invitations
JoinTeam::handle() ends with cancelOtherInvitations(): every other pending TeamInvitation for the student's email (pendingFor, which ignores case) is deleted, and its inviter gets Teams\InviteeJoinedAnotherTeam (mail and database, not queued). The notification is sent before the delete, and a failed send is logged, never allowed to undo the join. It mirrors CloseOtherInvitations for project invitations. A student is on one team, so the other invitations could never be accepted, and their senders were left waiting.
