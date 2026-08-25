---
paths:
  - 'app/Http/Controllers/Student/**'
  - app/Http/Controllers/Student/StudentEducationController.php
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

## Education is a readable list; school_id/course_id on the profile are what clients filter by
student_educations holds the list a reader sees — free-text `school`, optional years, degree and area of study. student_profiles keeps its single school_id / course_id / year_level, and they are NOT redundant: RecruitController filters students by those foreign keys and the header line under the student's name is built from them.

So the two are edited in different dialogs on purpose. The education dialog writes the list; the "Profile details" dialog writes the three filterable columns.

StudentEducationController resolves `school_id` on each save by matching the typed name case-insensitively against the schools table, so an unlisted school is still accepted as plain text while a listed one stays filterable. Do not make `school` a required FK — plenty of schools here are on no list.

StudentEducation must declare `protected $table = 'student_educations'` — the inflector treats "education" as uncountable and otherwise looks for `student_education`.
