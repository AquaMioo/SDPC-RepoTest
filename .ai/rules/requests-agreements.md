---
paths:
  - 'app/Http/Requests/Agreements/**'
---

# Requests Agreements

## Objective/Scope proof needs a file; cover letters are 5-50 words
SubmitTaskRequest: a task in a work phase (anything but Turnover: Objective, Scope, or Design/Build on older agreements) needs a proof file (an image or a PDF); a file kept from the last submission counts. Turnover keeps the note-or-link-or-file rule. The submit dialog labels it "File" (fileRequired prop) there. Separately, ApplyToProjectRequest measures the cover letter in words (MIN_WORDS 5, MAX_WORDS 50, split on whitespace) with max:2000 characters kept only as a storage guard; student/project.tsx counts the same way and disables Send outside the range. Both asked by the owner, 2026-10-04.
