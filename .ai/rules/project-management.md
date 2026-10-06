---
paths:
  - 'resources/js/components/project-management/**'
---

# Project Management

## Verify opens a review dialog; Reject lives only there; the student reads the answer
Since 2026-10-07 (owner) the client's only button on a submitted task is Verify, which opens ReviewTaskDialog (task-dialogs.tsx): the description, what the student sent (note, link, ProofFile preview), a comment box, and Verify / Reject. Reject (POST agreements.tasks.send-back) exists nowhere else and needs the comment; Verify (POST agreements.tasks.verify) keeps an optional comment in review_note instead of clearing it. The student side gets Review Verification (verified) / Review Rejection (open with a review_note) buttons opening ReviewOutcomeDialog with the comment; the inline "Sent back:" line is gone. ProofFile lives in task-dialogs.tsx and phase-checklist imports it. TaskFormDialog shows the deadline field only on Turnover, and the PM page passes only the Turnover phase to PhaseTimeline (the Gantt).
