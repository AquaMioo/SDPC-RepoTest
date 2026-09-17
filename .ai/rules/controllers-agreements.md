---
paths:
  - 'app/Http/Controllers/Agreements/**'
---

# Controllers Agreements

## Milestone positions: delete and park before renumbering
agreement_milestones is unique on (agreement_id, position). AgreementController::syncMilestones therefore deletes the rows the client dropped FIRST, then bumps every survivor by POSITION_PARKING_OFFSET, and only then writes the new 1..n order. Writing the new order straight over the old one 500s the moment two milestones swap places, or a new row claims a position a later row is about to free.

Both route models must be declared on AgreementMilestoneController::update, in URL order: (Request, Team $currentTeam, Agreement $agreement, AgreementMilestone $milestone). Omit $agreement and Laravel fills positionally, putting the agreement id into $milestone as a string.

Do not reach for ->scopeBindings() on that route. It would also scope {agreement} under {current_team}, and for a student the current team is their own personal team, never the business the contract is with. The controller checks $milestone->agreement_id === $agreement->id instead.

## Project Management: student writes, client verifies, both need an active agreement
ProjectManagementController serves one screen for both roles (replaces student Workflow and client Project Process; the old student.workflow/student.process URLs redirect). AgreementPolicy decides: viewProgress (parties + the signing student's teammates, read-only), manageTasks (only the signing student), verifyTasks (only the client team) — all require AgreementStatus::Active, so nothing works before both signatures.

AgreementTaskController guards moves by the task's current status, not just the actor: edit/delete only Open, verify/send-back only Submitted, verified is final. Refusals are ValidationException on 'task' so the screen can say why. Every action checks the task/phase belongs to the URL's agreement (404).

Timeline drags write planned_starts_on/planned_ends_on only — starts_on/ends_on are signed terms. Proof files live on the public disk under task-proofs/ (NOT linked in config/filesystems.php) and are served only through agreements.tasks.proof. Change requests only exist before signing, so tasks never need carrying across versions.

## The MOA download is the school's blank PDF, served through an authenticated route
The "Contract vN · reference" tag on agreements/contract.tsx links to agreements.memorandum (AgreementController::memorandum). It downloads resources/documents/memorandum-of-agreement.pdf unchanged, named "<reference> Memorandum of Agreement.pdf", behind Gate 'view', and returns 404 for AgreementTemplate::Clauses. The testers chose the blank form, to print, fill in and sign by hand, over a generated PDF, so no PDF package is installed. A filled-in PDF would need barryvdh/laravel-dompdf, which requires the owner's approval. To replace the form, swap the file; don't move it under public/, because the route is what limits it to the agreement's parties.
