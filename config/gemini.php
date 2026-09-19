<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini Recommendation Model
    |--------------------------------------------------------------------------
    |
    | The model that reads a brief and a shortlist of student profiles and says
    | how well each one fits, in words a client can act on.
    |
    | IT IS AN IMPROVEMENT, NOT A DEPENDENCY. Set RECOMMENDATIONS_DRIVER=gemini
    | to switch it on; leave it and the platform matches exactly as it does
    | today. Even switched on, every failure — no key, a timeout, a refusal, a
    | reply that is not the shape we asked for — falls through to the computed
    | scorer rather than showing anybody an error. Matching is on the critical
    | path of the whole product, so it must never be able to go dark because a
    | Google service is having an afternoon.
    |
    */

    'api_key' => env('GEMINI_API_KEY'),

    /*
    | Flash rather than Pro on purpose: this is a ranking task over short
    | profile text, and the difference in judgement is not worth the difference
    | in latency and price when a client is waiting on a page render.
    |
    | Pinned to a version, not gemini-flash-latest. A ranking that quietly
    | changes its mind because Google shipped a new model is not something you
    | want to discover during a demo — an upgrade should be a commit.
    |
    | This was gemini-2.5-flash, which the API still LISTS but refuses with a
    | 404 for any key created after it was retired: "no longer available to new
    | users". So a listed model is not a usable one, and the only way to find
    | out is to call it.
    */
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),

    /*
    | Models to ask, in order, while the one before is busy.
    |
    | Google sheds load per model: 429, or 503 "This model is currently
    | experiencing high demand". A busy model is not a dead one, and the next
    | one along usually answers. Giving up on the first 503 put the whole site
    | on keyword matching for the cooldown below, several times a day.
    | Measured 2026-09-19 with the real matching prompt, three calls each:
    | gemini-3.6-flash 3/3 in about 4s (between those 503s), gemini-3.5-flash
    | 3/3 in 7-11s, gemini-3.7-flash 1/3, gemini-3.8-flash 0/3 — newer is
    | busier, not better.
    |
    | Only "busy" moves on. A refusal (400, 403, 404) would be refused again,
    | and every model shares the one timeout below. Comma-separated, pinned
    | like the model; empty means the one model only.
    */
    'fallback_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.5-flash')),
    ))),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    | Seconds to wait. Short, because the fallback below is a good answer that
    | costs a millisecond — waiting 30 seconds for a slightly better one is the
    | wrong trade on a page load.
    |
    | This is the budget for the whole question, not for each model: when the
    | first model is busy, the next one gets whatever time is left.
    */
    'timeout' => (int) env('GEMINI_TIMEOUT', 20),

    /*
    | How hard the model is allowed to think before answering.
    |
    | Gemini 3 reasons by default, and it is not free: a bare "reply with the
    | word ok" took 11.2 seconds against gemini-3.6-flash, which blew straight
    | through the old 12-second timeout before a real prompt was even tried.
    | The same call at "low" takes 2.5.
    |
    | Low is right for this work. Ranking a profile against a brief is a
    | judgement about overlap, not a problem that rewards deliberation, and
    | somebody is waiting on a page render.
    |
    | Note thinkingBudget — the Gemini 2.x way to say this — is rejected with
    | INVALID_ARGUMENT on 3.x. It is thinkingLevel now.
    */
    'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),

    /*
    | How many students go into one request. The prompt carries every
    | candidate, so this bounds both cost and latency; the rest are scored by
    | the computed engine and ranked below the ones the model saw.
    */
    'max_candidates' => (int) env('GEMINI_MAX_CANDIDATES', 30),

    /*
    | Minutes to keep an answer. A brief and a student profile both change
    | rarely, and the cache key is built from their updated_at stamps — so an
    | edit invalidates itself and nothing serves a stale match. Without this,
    | every page of the recruit screen would be a fresh paid call.
    */
    'cache_minutes' => (int) env('GEMINI_CACHE_MINUTES', 60),

    /*
    |------------------------------------------------------------------
    | Cooldown after a fault
    |------------------------------------------------------------------
    |
    | How long to stop asking after any fault. The fallback makes an outage
    | survivable; this makes it cheap. Without it every reader paid the full
    | timeout to rediscover the same outage, which put twenty seconds in
    | front of a page otherwise served in about two hundred milliseconds.
    |
    | Zero disables the cooldown and asks every time.
    |
    */

    'cooldown_minutes' => (int) env('GEMINI_COOLDOWN_MINUTES', 5),

];
