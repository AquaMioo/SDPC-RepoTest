---
paths:
  - 'app/Http/Responses/**'
---

# Responses

## Clearing Inertia history must happen after the session is invalidated
Inertia::clearHistory() does not act immediately — it leaves `inertia.clear_history` in the SESSION for the next Inertia response to pull. Fortify's logout fires the Auth Logout event and only then calls session()->invalidate(), so a listener on that event sets the flag and has it thrown away a moment later. It looks like it works and does nothing.

So it lives in App\Http\Responses\LogoutResponse (bound in FortifyServiceProvider), which runs after the invalidate. ProfileController::destroy is the other way out and calls it after its own invalidate. Both are pinned by tests/Feature/Auth/HistoryAfterLogoutTest.php.

Both portals share Fortify's logout route, so admin is covered by the same class.
