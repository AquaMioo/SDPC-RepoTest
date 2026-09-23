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

## Gemini: a 503 means busy, not down — hand over to the next model
Google sheds load per model with 429/503 "high demand" between successful calls. Giving up on the first one put the whole site on keyword matching for the 5-minute cooldown several times a day (sdpc.tech, 2026-09-19). ask() now walks gemini.model then gemini.fallback_models (default gemini-3.5-flash-lite, then gemini-3.1-flash-lite; the flash models 503 together) on 429/500/502/503/504 and on a connection timeout, all inside ONE gemini.timeout budget (12 s); a refusal (4xx) never moves on. Each model gets at most gemini.attempt_timeout (6 s) of that budget: overloaded, Google holds a request 17-21 s before its 503 (measured 2026-09-22 from both sites), so a model given the whole budget spent it on the refusal and the fallbacks were never asked. Measured that day: newer models (3.7, 3.8) were busier than 3.6, so do not "upgrade" the fallback to the newest listed model without calling it first. Also: rank()/rankBriefs() union the computed scores so anyone past max_candidates keeps a score instead of vanishing.

## Gemini is geo-blocked from the Azure demo VM (eastasia / Hong Kong)
On demo.sdpc.tech (Azure `eastasia`, egress from Hong Kong) every Gemini model returns
HTTP 400 `FAILED_PRECONDITION` — "User location is not supported for the API use."
The key is fine: the same key, verified by SHA-256, succeeds from Manila. Google does not
serve the Gemini API to Hong Kong, and Azure policy `sys.regionrestriction` blocks
`southeastasia`, so the region cannot simply be changed.

Two failures look alike and are not. HTTP 400 `FAILED_PRECONDITION` is the location block;
isBusy() does not count it, so the service gives up without asking the fallback models.
HTTP 429 `GenerateRequestsPerDayPerProjectPerModel-FreeTier` is real: gemini-3.6-flash allows
20 requests a day on the free tier, and sdpc.tech and demo.sdpc.tech share one key, so they
share the 20. A 429 is "busy", and the flash-lite fallbacks answer it.

The fix is the Cloudflare Worker `sdpc-gemini` (https://sdpc-gemini.sdpcccapstone.workers.dev),
set on the VM only as GEMINI_BASE_URL=<that URL>/v1beta. It works only because of targeted
placement (`placement.region = "aws:ap-southeast-1"`): a Worker called from Hong Kong otherwise
runs in HKG and gets the same 400. A redeploy that drops the placement metadata silently brings
the block back; the response header `cf-placement: remote-SIN` proves it is on. The Worker
relays only POST .../models/<model>:generateContent and only for SDPC's key, which it holds as a
SHA-256, so rotating GEMINI_API_KEY means updating KEY_SHA256 in the Worker too. Its source
lives on Cloudflare (Workers & Pages -> sdpc-gemini), not in this repo.

## Gemini speed: deferred ranking, quota rest, fingerprinted cache, temperature 0
Board and Recruit paint first; the ranked list (projects/students, highlight, matchingEnabled) arrives as ONE deferred group named `ranking` behind components/sdpc/list-skeleton.tsx. Tests read it with loadDeferredProps('ranking', …) — asserting those props on the first response fails.

rankedByScope returns its scores with the page so the cards reuse them; do not score a brief twice (it used to call Gemini twice per Recruit search).

temperature 0, so the same inputs give the same ranking. Board and capstone cache keys include fingerprint($briefs) of the current postings, so a new or edited posting misses the cache instead of being invisible until expiry.

A 429 whose body says `PerDay` rests that model until midnight America/Los_Angeles (when Google resets the daily quota); any other 429 rests for the body's retryDelay (default 60s). Rests are cache keys `gemini.exhausted.<model>`. When every model is resting, ask() falls back to computed matching immediately with log reason `exhausted` — no HTTP call.

No background pre-warming: the free quota (20/day on gemini-3.6-flash, shared by sdpc.tech and demo.sdpc.tech) would be spent on rankings nobody opens.

## Overload is per model: rest the busy one, stop everything only when none is left
Measured from sdpc.tech and the VM on 2026-09-22/23: gemini-3.6-flash answered every probe in 1.3-4.5 s while gemini-3.5-flash-lite returned 503 or held the connection open with 0 bytes until the 30 s cap. Google sheds load per model, so a fault is rarely an outage.

A model that 503s or stalls now rests for gemini.busy_rest_seconds (120) under the `gemini.busy.<model>` key, which models() filters like the quota key. beginCooldown() is skipped while models() still returns somebody: the global 5-minute cooldown used to start on any failed question, so one busy lite model put the whole site on keyword matching for five minutes — that is what "sometimes the AI works, sometimes it doesn't" was. A cooldown of zero still means ask every time: it disables the busy rests too.

Only a refusal (4xx) or an exception is treated as everyone's problem, because those are about the key, the location or the payload. tests/Feature/Matching/GeminiRecommendationTest pins both halves.

## The main model is gemini-3.5-flash-lite, for its quota
Switched from gemini-3.6-flash on 2026-09-24. On the free tier 3.6 Flash allows 5 RPM and 20 requests a day, shared by sdpc.tech and demo.sdpc.tech; AI Studio showed 26/20 used, so the site spent most days on 429s and the fallbacks. 3.5 Flash Lite allows 15 RPM and 500 a day. Order is now 3.5-flash-lite, then 3.1-flash-lite, then 3.6-flash as a last resort (it rests until midnight Pacific once its day is spent).

GEMINI_MODEL is pinned in every .env (local, C:\sites\sdpc, the VM's /var/www/sdpc), so changing the default in config/gemini.php alone changes nothing live — edit all three. The prompt, payload and parsing are the same for every model, and any answer that does not parse falls back to the computed scorer.

Earlier notes that said "no quota errors" read only the failure log: a 429 that a fallback then answered is never logged as a failure. Check AI Studio's Rate Limit page for real quota use.

## "Match my capstone" must read the capstone even without the model
GeminiRecommendationService::projectScoresForText falls back to ComputedRecommendationService::projectScoresForText, never to scoresForStudent. The computed side compares the capstone with each posting through MatchingEngine::compareScopes (60% share of the posting's skills the capstone implies, 40% share of the capstone's domain words the posting also uses). It only returns the saved-profile ranking when the typed words imply no skills at all.

Before 2026-09-24 both halves ignored the text: the Gemini fallback went straight to the profile, and the computed scorer called engine->score($capstone, $profile) — the same number for every posting. So whenever the model was busy (most afternoons on the free tier) the capstone search reordered nothing, while the client-side search, whose fallback does read the words, kept working. tests/Feature/Matching/StudentRecommendationTest::test_the_capstone_still_orders_the_board_when_the_model_is_unavailable pins it.
