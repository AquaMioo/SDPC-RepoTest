---
paths:
  - 'app/Http/Controllers/Student/**'
---

# Student

## Declare Team $currentTeam before any other route model
Every student and client route is mounted on the `{current_team}` prefix. A controller method taking a second route model must declare `Team $currentTeam` ahead of it — e.g. `apply(Request $request, Team $currentTeam, Project $project)`. Omit it and Laravel passes the team slug positionally into the next parameter, giving "Argument #2 must be of type Project, string given".

## Student profile rows are created on first visit; the client list hides contact details
Registration does not mint a student_profiles row. StudentProfileController and PortfolioItemController both resolve it with StudentProfile::firstOrCreate(['user_id' => ...]), so a student who has never opened the screen gets a profile rather than a 404. Anything else that needs the profile should do the same, not assume one exists.

Portfolio ownership is enforced by resolving through the signed-in student's own profile — an id belonging to somebody else 404s rather than needing a policy. PortfolioItemController::destroy takes a plain Request, not SavePortfolioItemRequest: a DELETE has no title to validate.

ClientDirectoryController::show deliberately sends no phone number and no contact email. Messaging on this platform waits for an application to link the two sides, and publishing an address here would route around that for every verified business at once. There is a test pinning it.

## Rank before you page, and only when there is something to rank by
ProjectBoardController::index read `sort` off the query string and handed it to the screen but never applied it — "Recommended" and "Newest" returned the same list, so the tab did nothing.

It now branches: SORT_RECOMMENDED with scores goes through rankedByScore(), which fetches, sorts by compatibility (published_at breaking ties) and hand-pages with LengthAwarePaginator. Everything else keeps the cheaper `latest('published_at')->paginate()`.

Sorting has to happen before paging or "recommended" only means "best on whichever page you are looking at" — the same reason RecruitController::rankedByScope exists. With nothing scored it falls back to date order rather than shuffling: an empty ranking is not a ranking.
