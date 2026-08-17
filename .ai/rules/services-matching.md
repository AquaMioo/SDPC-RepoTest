---
paths:
  - app/Services/Matching/SkillInference.php
---

# Services Matching

## Stem to a fixed point, and never pad the needle
Both the vocabulary key and the searched text go through normalise()/stem(), so the reduction must be order-independent — a single pass sent "orders" to "ord" (via -ers) but "ordering" only to "order" (via -ing) and the forms stopped matching. The loop runs until stable. Also: normalise() must not pad with spaces; the result is used inside a word-boundary regex, and a trailing space put the boundary in the wrong place and matched nothing. Use `php artisan matching:explain "phrase"` to check a change.
