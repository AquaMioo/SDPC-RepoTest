---
paths:
  - 'app/Actions/Agreements/**'
---

# Agreements

## Signing starts the project, not acceptance
RespondToApplication no longer moves a project to in_progress. Accepting a student calls DraftAgreement and leaves the posting open; SignAgreement activates the agreement and starts the project when the SECOND signature lands. This follows the capstone PDF: the Terms and Agreements Form appears before collaboration begins.

The one-build-per-student cap still binds at acceptance, not at signing — a student who has been accepted is spoken for, and letting a second client accept them mid-negotiation would hand them two contracts to choose between.

Change requests supersede, they never edit. SupersedeAgreement marks the standing row superseded, points superseded_by forward and clones to version + 1 with no signatures. If terms could move after somebody signed, the contract log would be worthless.

## Agreements read as the school's Memorandum of Agreement; signed ones keep their clauses
agreements.template (AgreementTemplate) decides the wording. Memorandum is the default for every new agreement and every superseded version: the text lives in config('agreements.memorandum'), and PresentAgreement fills :project/:starts and the parties into the `memorandum` prop. Clauses is kept for agreements someone signed before the MOA (migration 2026_09_16_184127), and they still show the three editable clause columns. Each template has its own acknowledgement keys (config acknowledgements vs clause_acknowledgements), and SignAgreementRequest and SignAgreement read them via $agreement->template. Never switch a signed agreement's template, because a signature must stay with the words it was given for.

SignAgreement no longer requires a milestone amount, only an end date. The pricing UI was removed on 2026-08-31 and milestones are seeded at 0, so requiring an amount meant no agreement made on the site could be signed. The MOA also leaves payment to the parties. The clause columns stay (nullable in SaveAgreementRequest, like the amount column).

## Turnover is the last phase, and its end is the final deadline
AgreementMilestone::isTurnover() is decided by POSITION (the highest on the agreement), never by name — clients rename phases. Agreement::turnoverPhase() / finalDeadline() (Turnover's scheduledEndsOn, the working schedule, not the signed ends_on) are the only way to read it.

Rules that hang off it: every non-Turnover task needs a due_on on or before the final deadline (SaveTaskRequest; Turnover tasks may omit it). UpdatePhaseScheduleRequest::after() lets Design and Build overlap, but Turnover may not overlap any other phase, and its end cannot be dragged on the Gantt (phase-timeline.tsx disables that handle too). The final deadline moves only through an approved deadline request. SummariseProgress exposes finalDeadline, daysToFinalDeadline and overdueTaskCount (AgreementTask::isOverdue).

## A deadline, once set, moves only with the client's approval
AgreementTaskController::update refuses to change a due_on that is already set ("Ask for a change instead"). The student side (manageTasks: signer + teammates) asks through RequestDeadlineChange::forTask / forFinalDeadline, which stores a Pending DeadlineChangeRequest (task_id OR milestone_id, previous_on, proposed_on, reason). At most one pending ask per deadline. The client (verifyTasks) answers with DecideDeadlineChange::approve / decline; the student side may withdraw (destroy) while pending.

Approval re-checks the rules under a lock, because other dates may have moved since the ask: a task date must still be on or before the final deadline, and a new final deadline must still be after every task deadline and Turnover's start. Approving writes due_on or Turnover's planned end only — the signed starts_on/ends_on never change. Notifications: DeadlineChangeRequested (deadline.requested, to the business) and DeadlineChangeDecided (deadline.decided, to the student side); both have lines in PresentNotification. Both dashboard calendars show task deadlines and the dates asked for.

## Completing a project is the client's move and releases everyone
CompleteProject (POST agreements/{agreement}/completion, Gate 'complete') locks the project row, refuses anything not InProgress, and sets the project AND every active agreement on it to Completed with completed_at. It is Completed, not Archived — Archived means a posting the client withdrew, and the landing page counts Completed as delivered work.

Nothing else needs undoing: isLockedToProject, Team::isBuilding and ProjectPolicy::create all read ProjectStatus::isUnfinished(), so the signer, the teammates and the business are free from that commit. Notifications go out after the commit: ProjectStatusChanged to the business, ProjectCompleted (project.completed) to Agreement::studentSide() — signer plus teammates.

Teammates are NOT added to the project chat automatically: group chats stay invite-only (.ai/rules/messaging.md) unless the testers decide otherwise.
