---
paths:
  - 'app/Services/Recommendation/**'
---

# Recommendation

## Check for the ScoresFreeText contract, never for a concrete driver
RecruitController decides whether the search box RANKS ("POS to my website system") or merely FILTERS ("Reyes"). It used to make that decision with `$recommendations instanceof ComputedRecommendationService`, which meant the day a second capable driver was bound the whole scope-search feature silently turned itself off — no error, the search just stopped finding anyone.

It now tests for App\Services\Recommendation\ScoresFreeText. Any driver that can answer free text implements it; StoredRecommendationService deliberately does not, because a table of precomputed scores cannot answer a brief nobody has written yet.

Adding a driver: implement RecommendationService, add ScoresFreeText if it can score free text, and register it in AppServiceProvider's match on config('recommendations.driver').

GeminiRecommendationService falls back to ComputedRecommendationService on every fault — no key, timeout, non-2xx, unparseable reply, or a reply naming nobody real. Matching is on the critical path of the product; it must never be able to go dark because an external service is slow. Tests in tests/Feature/Matching/GeminiRecommendationTest.php pin each failure mode.
