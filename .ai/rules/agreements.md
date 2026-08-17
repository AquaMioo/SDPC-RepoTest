---
paths:
  - 'app/Actions/Agreements/**'
---

# Agreements

## Signing starts the project, not acceptance
RespondToApplication no longer moves a project to in_progress. Accepting a student calls DraftAgreement and leaves the posting open; SignAgreement activates the agreement and starts the project when the SECOND signature lands. This follows the capstone PDF: the Terms and Agreements Form appears before collaboration begins.

The one-build-per-student cap still binds at acceptance, not at signing — a student who has been accepted is spoken for, and letting a second client accept them mid-negotiation would hand them two contracts to choose between.

Change requests supersede, they never edit. SupersedeAgreement marks the standing row superseded, points superseded_by forward and clones to version + 1 with no signatures. If terms could move after somebody signed, the contract log would be worthless.
