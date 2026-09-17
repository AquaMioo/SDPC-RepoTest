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
