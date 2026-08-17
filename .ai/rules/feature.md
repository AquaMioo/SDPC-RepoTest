---
paths:
  - 'tests/Feature/**'
---

# Feature

## Pin current_team explicitly in team-scoped route() calls in tests
User::switchTeam() sets URL::defaults(['current_team' => ...]) globally (app/Concerns/HasTeams.php). UserFactory's afterCreating hook calls it, so any factory that creates an incidental user — e.g. ProjectFactory's created_by — overwrites the default with a throwaway team's slug.

In production this is fine: SetTeamUrlDefaults re-runs per request from the authenticated user. In tests it is not. A bare route('projects.index') can silently point at the wrong team and return 403 from EnsureTeamMembership, which looks like an authorization bug but is not.

Always pass the team explicitly: route('projects.show', ['current_team' => $user->currentTeam, 'project' => $project]).

## The landing page hits the database, so tests that GET / need RefreshDatabase
`/` used to be `Route::inertia('/', 'welcome')` and touched nothing. It is HomeController now and counts users, projects and client profiles, so any test that requests `/` without RefreshDatabase fails with "no such table: users" — a 500 that looks nothing like a missing trait. Two scaffold stubs (ApplicationReviewTest, RecruitTest) were caught by this.
