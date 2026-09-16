---
paths:
  - 'app/Actions/Teams/**'
---

# Teams

## One team per student: JoinTeam replaces a solo team, GiveOwnTeam backfills
A student is on exactly one team. JoinTeam (used by TeamInvitationController::accept) dissolves the student's own team when nobody else is on it (memberships, its sent invitations, removal votes, conversations.student_team_id → null, soft delete), and refuses when they lead a non-solo team or already sit on someone else's. JoinTeam::refusal() is also asked at send time in TeamInvitationController::store; only students can be invited.

Because the own team is gone after joining, leaving (TeamController::leave) or being voted out (VoteToRemoveMember) calls GiveOwnTeam, which creates a fresh personal team. Do not go back to $user->personalTeam() for that — every signup team is is_personal, so for a joined member it returns the team they just left. TeamPolicy::leave is therefore "member and not owner", no longer "not is_personal". After accepting, redirect with an explicit current_team: the URL defaults still point at the dissolved team.

## A group chat is at most five people: a team of four plus the client
Team::MAX_MEMBERS = 4 counts the leader, and only students can be invited (JoinTeam::refusal), so a client's team is only the client. Conversation::participants() = the thread's student + their team + the client = 5 at most, but only while the thread's student is still on the team the thread names. So leaving (TeamController::leave) and being voted off (VoteToRemoveMember::remove) both call Conversation::releaseFromTeam(), which drops student_team_id on the leaver's own threads. Without it, the chat holds the leaver plus a refilled team of four, which is six. Migration 2026_09_16_133431 fixed the threads left that way before the release existed. The call grid (video-call.tsx gridColumns) is sized for at most four other tiles because of this.
