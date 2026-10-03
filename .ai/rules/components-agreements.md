---
paths:
  - 'resources/js/components/agreements/**'
---

# Components Agreements

## One MemorandumDocument draws the MOA for both the screen and the printed copy
memorandum-document.tsx renders the SDPC MOA (sections I-X from the `moa` prop) for agreements/contract.tsx (with `editing`: Add requirement / Add service, the author's Edit/Remove) and agreements/printable.tsx (read-only). Never draw the MOA a second way: document fidelity depends on the two being the same component. Anything screen-only (controls, author labels, the template's "add more here" prompts and Section VII's sample text) must carry `no-print` and live outside the printed wording, so an optional section nobody added to prints with no placeholder. Every part of every section is numbered (a numbered item may nest an a., b. list); additions continue the section's last numbered list (IV starts at 6, VIII at 4, IX at 4). Section VII prints each service as a numbered "**Objective**: scope", with no Objective/Scope labels. Styles live in app.css under .moa-* and @page moa (A4, Times New Roman, "SDPC MOA" footer).
