---
paths:
  - .env.example
---

# General

## Railway variables use ${{...}}, .env files use ${...} — do not copy between them
`VITE_REVERB_HOST="${REVERB_HOST}"` is correct in a .env file: phpdotenv and Vite both interpolate a single-brace `${VAR}`. Railway's variables use `${{Service.VAR}}` with two braces and treat the single-brace form as literal text.

Someone copied the .env.example Reverb block into the Railway dashboard, so the production browser bundle shipped with its WebSocket host set to the literal string "${REVERB_HOST}". It failed silently and at build time, because VITE_ values are compiled into the bundle rather than read at runtime — nothing in the app logs, and the variable looked right in the dashboard.

On Railway, write VITE_ values out in full. To reference another service's variable, the syntax is `${{SDPC-RepoTest.REVERB_APP_KEY}}`.

Two related traps on the same host: VITE_ variables only reach the browser on a rebuild, never on a variable change alone; and outbound SMTP (587/465/2525) is blocked entirely, which is why mail goes over Brevo's HTTPS API.
