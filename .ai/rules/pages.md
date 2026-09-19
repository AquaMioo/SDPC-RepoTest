---
paths:
  - 'resources/js/pages/**'
---

# Pages

## A page shell grows into a wide screen; keep the clamp, not a bare width
Every screen's shell is capped with `clamp(<floor>px, 100vw - 320px, 1600px)` rather than a bare pixel width — inline as `maxWidth: 'clamp(1320px, 100vw - 320px, 1600px)'`, or in Tailwind as `max-w-[clamp(1320px,100vw_-_320px,1600px)]` (underscores are the space escape; without them calc-style subtraction is invalid CSS).

The floor is the width the screen was drawn at, so nothing changes at or below ~1380px. Past that the shell grows, keeping 160px of gutter each side, up to a shared 1600px ceiling. Before this, zooming out stranded every page in a 1060–1320px island — 41% of a 2560px screen with 750px of dead margin each side; it is 62% now.

Do not "simplify" one of these back to a plain number, and keep the layout bars (client-layout, admin-layout, settings/layout) on the same formula or the header stops lining up with the content under it.

## No hourly rates or proposed rates anywhere
SDPC does not price student work (decided 2026-09-19; the agreement pricing UI went on 2026-08-31, billing ships switched off). The ₱/hr field, the "rate per hour" on applying, and "Proposed rate" on the applicant list were removed, and the requests no longer accept hourly_rate or proposed_rate. The student_profiles.hourly_rate and applications.proposed_rate columns stay only so old rows are not destroyed; never read, show or write them again. tests/Feature/Client/RecruitOutreachTest (neither_the_profile_nor_the_applicants_show_a_price) pins it.
