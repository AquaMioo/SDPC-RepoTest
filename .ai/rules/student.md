---
paths:
  - 'app/Http/Controllers/Student/**'
---

# Student

## Declare Team $currentTeam before any other route model
Every student and client route is mounted on the `{current_team}` prefix. A controller method taking a second route model must declare `Team $currentTeam` ahead of it — e.g. `apply(Request $request, Team $currentTeam, Project $project)`. Omit it and Laravel passes the team slug positionally into the next parameter, giving "Argument #2 must be of type Project, string given".
