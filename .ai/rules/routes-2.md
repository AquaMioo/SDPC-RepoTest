---
paths:
  - 'routes/**'
---

# Routes 2

## Exempt routes from CSRF with PreventRequestForgery, not ValidateCsrfToken
On Laravel 13 the web group guards with Illuminate\Foundation\Http\Middleware\PreventRequestForgery; ValidateCsrfToken is now a deprecated subclass of it, so ->withoutMiddleware(ValidateCsrfToken::class) excludes nothing and the route still answers 419. That shipped on webhooks/paymongo (2026-10-10) and every test passed, because CSRF is skipped entirely under unit tests. Use withoutMiddleware(PreventRequestForgery::class). AddendumPaymentTest::test_the_webhook_is_not_behind_the_csrf_check pins it by building the HTTP kernel (only it registers the web group) and reading gatherRouteMiddleware.
