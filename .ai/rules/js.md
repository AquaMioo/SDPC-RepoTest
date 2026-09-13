---
paths:
  - 'resources/js/**'
---

# Js

## Screens are built with inline styles, so responsiveness comes from classes not media queries
Nocturne was drawn for a desktop canvas and had no @media rules at all. Screens set their geometry in inline `style={{...}}`, and an inline style beats any selector — so you cannot make a page responsive by adding CSS. The page has to hand its geometry to one of the shared classes at the foot of resources/css/nocturne.css:

- `.page-shell` — the page gutter. 16px on a phone, 32px on a desktop. Keep `maxWidth` inline; that part is harmless.
- `.split` (+ `.split-end` to put the rail on the right) — a main column with a rail. Stacked below 1024px; pass the rail width as `['--rail' as string]: '300px'`.
- `.app-bar` / `.app-bar-nav` / `.app-bar-actions` — the header. One row on a desktop; on a phone the brand and icons hold the top line and the nav scrolls under them.
- `.table-wrap` / `.scroll-x` — anything that cannot shrink scrolls in its own box instead of pushing the page sideways.

Two traps: `gridTemplateColumns: 'repeat(N,1fr)'` does NOT shrink past its content — use `repeat(auto-fit, minmax(190px,1fr))`, or `minmax(0,1fr)` when the column count is fixed (a 7-day calendar). And a fixed `width: 460` on a card overflows a 375px phone — write `width: '100%', maxWidth: 460`.

Inline page gutters use `clamp(16px, 4vw, 32px)` rather than a bare `32px`.

## A new top-level page needs a case in app.tsx's layout switch
The layout resolver in resources/js/app.tsx ends in `default: return AppLayout`. Any page name that matches none of the cases above it is wrapped in the signed-in chrome, which reads the authenticated user and throws "Cannot read properties of null (reading 'avatar')" for a guest. The result is a blank white page with the content rendered nowhere.

Nothing server-side sees this. The Inertia response is a correct 200 with the right component and props, so feature tests pass while every visitor gets a white screen. The legal pages shipped this way until they were opened in a browser.

A public page that brings its own PublicLayout must get an explicit `case name === '<page>': return null;`, the way welcome, admin/login, auth/login and auth/register already do.
