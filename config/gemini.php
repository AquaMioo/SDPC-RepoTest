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
    | Google service is having an afternoon. Same reasoning as SheerID.
    |
    */

    'api_key' => env('GEMINI_API_KEY'),

    /*
    | Flash rather than Pro on purpose: this is a ranking task over short
    | profile text, and the difference in judgement is not worth the difference
    | in latency and price when a client is waiting on a page render.
    */
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    | Seconds to wait. Short, because the fallback below is a good answer that
    | costs a millisecond — waiting 30 seconds for a slightly better one is the
    | wrong trade on a page load.
    */
    'timeout' => (int) env('GEMINI_TIMEOUT', 12),

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

];
