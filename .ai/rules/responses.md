---
paths:
  - 'app/Http/Responses/**'
---

# Responses

## Clearing Inertia history must happen after the session is invalidated
Inertia::clearHistory() does not act immediately — it leaves `inertia.clear_history` in the SESSION for the next Inertia response to pull. Fortify's logout fires the Auth Logout event and only then calls session()->invalidate(), so a listener on that event sets the flag and has it thrown away a moment later. It looks like it works and does nothing.

So it lives in App\Http\Responses\LogoutResponse (bound in FortifyServiceProvider), which runs after the invalidate. ProfileController::destroy is the other way out and calls it after its own invalidate. Both are pinned by tests/Feature/Auth/HistoryAfterLogoutTest.php.

Both portals share Fortify's logout route, so admin is covered by the same class.

## Signing in rotates Inertia's history key
Inertia rebuilds Back and Forward from encrypted history state without contacting the server — measured in a browser, both directions issued zero network requests. Response headers and middleware cannot reach a request that is never made.

LoginResponse, PasskeyLoginResponse and TwoFactorLoginResponse therefore call forgetHistoryFromBeforeSignIn() (Inertia::clearHistory) from the RedirectsToCurrentTeam concern. Entries written before sign-in stop being decryptable, so a later Back becomes a real request instead of a redraw.

It must run after Fortify has regenerated the session. clearHistory() leaves a flag for the next Inertia response, and regenerating afterwards throws it away — the same constraint LogoutResponse documents.
