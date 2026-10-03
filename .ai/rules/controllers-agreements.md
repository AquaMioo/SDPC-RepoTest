---
paths:
  - 'app/Http/Controllers/Agreements/**'
---

# Controllers Agreements

## Milestone positions: never write a position somebody else still holds
agreement_milestones is unique on (agreement_id, position). The client's form no longer reorders, adds or drops phases (AgreementController::scheduleMilestones writes dates only), and Section VII services are requirement rows, not phases, so nothing renumbers phases today. Any new code that renumbers must free a position before something claims it.

Both route models must be declared on AgreementMilestoneController::update, in URL order: (Request, Team $currentTeam, Agreement $agreement, AgreementMilestone $milestone). Omit $agreement and Laravel fills positionally, putting the agreement id into $milestone as a string.

Do not reach for ->scopeBindings() on that route. It would also scope {agreement} under {current_team}, and for a student the current team is their own personal team, never the business the contract is with. The controller checks $milestone->agreement_id === $agreement->id instead.

## Project Management: student writes, client verifies, both need an active agreement
ProjectManagementController serves one screen for both roles (replaces student Workflow and client Project Process; the old student.workflow/student.process URLs redirect). AgreementPolicy decides: viewProgress (parties + the signing student's teammates), manageTasks (the signing student AND the members of the team the signer owns — they are locked to the build, see User::isLockedToProject), verifyTasks (only the client team), complete (the client with ManageProjects). All of them require AgreementStatus::Active, so nothing works before both signatures — except viewProgress, which also allows Completed so the finished project stays readable (the screen shows a read-only banner and a Completed list).

AgreementTaskController guards moves by the task's current status, not just the actor: edit/delete only Open, verify/send-back only Submitted, verified is final. Refusals are ValidationException on 'task' so the screen can say why. Every action checks the task/phase belongs to the URL's agreement (404).

Timeline drags write planned_starts_on/planned_ends_on only — starts_on/ends_on are signed terms. Proof files live on the public disk under task-proofs/ (NOT linked in config/filesystems.php) and are served only through agreements.tasks.proof. Change requests only exist before signing, so tasks never need carrying across versions.

## Two views of the MOA: the blank template (PDF) and the finished copy (printable page)
agreements.memorandum (AgreementController::memorandum) downloads the blank form for the agreement's wording, AgreementTemplate::blankForm(): resources/documents/sdpc-memorandum-of-agreement.pdf for the SDPC memorandum, memorandum-of-agreement.pdf for the earlier one, 404 for Clauses. It is named "<reference> Memorandum of Agreement.pdf" and sits behind Gate 'view'; don't move the files under public/, the route is what limits them to the parties.

The finished copy is agreements.printable (AgreementController::printable, SDPC template only): Inertia page agreements/printable with no layout (app.tsx), drawing the same MemorandumDocument component the contract screen edits, so what is added on screen is exactly what prints. The browser prints it or saves it as a PDF; no PDF package is installed (barryvdh/laravel-dompdf would need the owner's approval). Print CSS in app.css: @page moa (A4, "SDPC MOA" / page number footer), sections IV-X on a new sheet, .no-print hides controls and the "add more here" prompts, so an optional section nobody added to prints with no placeholder.
