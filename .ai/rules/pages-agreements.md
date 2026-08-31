---
paths:
  - 'resources/js/pages/agreements/**'
---

# Pages Agreements

## Money is not drawn anywhere; the Extension will carry it
Pricing is deliberately absent from the agreement screens. The card on agreements/show.tsx is "Milestones" — phase-name inputs only, no amount column and no Total row — and the per-row peso total is gone from agreements/index.tsx. Money is out of scope until the Extension, which is what will handle it.

Only the UI was removed. agreement_milestones.amount, the required|integer|min:0 rule in SaveAgreementRequest, Agreement::total_amount (a SUM over the milestones) and the totalAmount prop on both pages all stay, and the edit form still posts amount: 0 for every row. So pricing returns by restoring these two components — do not "clean up" the columns, the validation or the prop, and do not add a migration to drop them.
