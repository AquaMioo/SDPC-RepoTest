---
paths:
  - 'tests/Feature/Matching/**'
---

# Matching

## Pin every field the scope reads, not just the description
ScopeProfile::fromProject concatenates title, category, industry, description AND objectives. ProjectFactory fills category/industry/objectives with faker prose that infers skills of its own, so a matching test that only sets `description` passes alone and fails in a full run. Pin category, industry, objectives, experience_level and the preferred_* columns in any test asserting a ranking order.
