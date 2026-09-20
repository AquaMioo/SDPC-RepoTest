---
paths:
  - 'resources/js/components/**'
---

# Components

## A DialogFooter inside a Form needs its own top margin
`DialogContent` spaces its children with `gap-4`, but an Inertia `<Form>` is a single child — so a `DialogFooter` written inside the form gets no gap at all and sits flush against the field above it. Measured at 0px: the Save button's border landed exactly on the select's bottom border, which is the "Overlapping" QA raised against the Add language dialog.

Footers nested in a form carry `className="mt-4 gap-3"`; footers that are direct children of the dialog carry `gap-3` only, because `gap-4` already spaces them. If you add a dialog, check which case you are in rather than copying whichever line you saw last.

## Every avatar goes through UserAvatar, fed by User::avatarUrl()
`users.avatar` holds only the URL the OAuth provider handed back at sign in; an upload lives in `users.avatar_path`. `User::avatarUrl()` is the only thing that knows the upload wins, so a screen reading either column shows a stale face.

Server: send `'avatarUrl' => $user->avatarUrl()`. Never `$user->avatar`. Client: draw it with `components/sdpc/user-avatar.tsx`, which falls back to initials — not a stock silhouette.

This was broken in four places at once (2026-09-20): TeamController sent the raw column, user-info.tsx read `user.avatar`, and the recruit grid and applicant list drew a generic `UserIcon` for everybody. tests/Feature/AvatarEverywhereTest gives each account a Google URL *and* an upload and insists on the upload, which is the case that catches a column read.

Notification rows are the one exception that cannot be fully fixed: the payload is a historical record, so rows written before `actorId()` existed carry no id and keep their coloured initials. PresentNotification::avatarsFor() resolves the rest in one query — do not look a user up inside handle(), that is a query per row.
