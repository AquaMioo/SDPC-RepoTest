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

## Gemini: a listed model is not a usable one, and 3.x thinks by default
Two traps that cost an evening and are invisible from the code.

gemini-2.5-flash is still returned by the ListModels endpoint with generateContent in its supportedGenerationMethods, and still 404s: "This model is no longer available to new users." Retired models stay listed for the keys that already had them. So a model appearing in the list proves nothing — the only test is calling it, and the 404 body names the replacement.

Gemini 3.x reasons by default and it is not cheap. A bare "reply with the single word: ok" against gemini-3.6-flash took 11.2 seconds, which blew a 12-second timeout before any real prompt was tried; the same call with generationConfig.thinkingConfig.thinkingLevel = "low" took 2.5. Ranking a profile against a brief does not reward deliberation and somebody is waiting on a page render, so it is pinned to "low" in config/gemini.php. Note thinkingBudget — the 2.x spelling — is rejected with INVALID_ARGUMENT on 3.x.

The model is pinned to a version rather than gemini-flash-latest on purpose: a ranking that changes its mind because Google shipped something new should be a commit, not a surprise during a demo.
