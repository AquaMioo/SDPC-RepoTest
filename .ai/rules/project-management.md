---
paths:
  - 'resources/js/components/project-management/**'
---

# Project Management

## Verify opens a review dialog; Reject lives only there; the student reads the answer
Since 2026-10-07 (owner) the client's only button on a submitted task is Verify, which opens ReviewTaskDialog (task-dialogs.tsx): the description, what the student sent (note, link, ProofFile preview), a comment box, and Verify / Reject. Reject (POST agreements.tasks.send-back) exists nowhere else and needs the comment; Verify (POST agreements.tasks.verify) keeps an optional comment in review_note instead of clearing it. The student side gets Review Verification (verified) / Review Rejection (open with a review_note) buttons opening ReviewOutcomeDialog with the comment; the inline "Sent back:" line is gone. ProofFile lives in task-dialogs.tsx and phase-checklist imports it. TaskFormDialog shows the deadline field only on Turnover, and the PM page passes only the Turnover phase to PhaseTimeline (the Gantt).

## Objective & Scope pages four tasks; the client sees no proof files there
Since 2026-10-08 (owner) PhaseChecklist pages Objective & Scope four tasks at a time (TASKS_PER_PAGE, usePagination + PageNumbers from components/sdpc/page-numbers); Turnover stays on one page. Reordering uses the task's index in the whole phase (firstIndex + offset), never its index on the page. In Objective & Scope the client's rows hide the proof file (showsProofFile = !(canVerify && !phase.isTurnover)): they open it through Verify. The student side and Turnover still show files.
