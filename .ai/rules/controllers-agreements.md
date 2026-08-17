---
paths:
  - 'app/Http/Controllers/Agreements/**'
---

# Controllers Agreements

## Milestone positions: delete and park before renumbering
agreement_milestones is unique on (agreement_id, position). AgreementController::syncMilestones therefore deletes the rows the client dropped FIRST, then bumps every survivor by POSITION_PARKING_OFFSET, and only then writes the new 1..n order. Writing the new order straight over the old one 500s the moment two milestones swap places, or a new row claims a position a later row is about to free.

Both route models must be declared on AgreementMilestoneController::update, in URL order: (Request, Team $currentTeam, Agreement $agreement, AgreementMilestone $milestone). Omit $agreement and Laravel fills positionally, putting the agreement id into $milestone as a string.

Do not reach for ->scopeBindings() on that route. It would also scope {agreement} under {current_team}, and for a student the current team is their own personal team, never the business the contract is with. The controller checks $milestone->agreement_id === $agreement->id instead.
