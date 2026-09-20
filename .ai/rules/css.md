---
paths:
  - 'resources/css/**'
---

# Css

## Nocturne owns --color-accent, so shadcn's accent utilities live under --color-ui-accent
nocturne.css redefines `--color-accent` on `[data-mod]`, and `[data-mod]` is set on `<html>` for every non-admin page. That is the same variable Tailwind generates `bg-accent` from, so `hover:bg-accent` painted the solid brand olive (#64764f) instead of the pale #dfe8d2 tint the pair was drawn as — and a ghost button's icon (`--accent-foreground`, #2f3928) disappeared into it. Every shadcn ghost button, dropdown item, select item and toggle was affected; QA found it as a green blob where the teams-page pencil should be.

The shadcn hover pair is therefore named `--color-ui-accent` / `--color-ui-accent-foreground` in app.css's `@theme`, and the utilities are `bg-ui-accent` / `text-ui-accent-foreground`. Do not reintroduce a `--color-accent` token in `@theme`: nocturne wins the cascade and the utility silently resolves to the brand colour.

`--color-neutral-100..900` collides the same way — nocturne inverts the ramp under `[data-mod="user"]`, so `text-neutral-900` is light there, not dark. Anything still using Tailwind's neutral scale (app-header.tsx, nav-footer.tsx) is rendering with an inverted palette.

## A hover that shifts to --color-accent-300 is invisible on the user palette
Under `[data-mod="user"]` — which app.blade.php stamps on every non-admin page — the accent ramp runs the opposite way to the dark theme's: 100 is the darkest olive and `--color-accent-300` is `#64764f`, the exact value of `--color-accent`. So nocturne.css's generic `a:not([data-slot='button']):hover { color: var(--color-accent-300) }` resolves to the colour the link already had, and every plain link on the student and client sides had no hover at all. A `:where([data-mod='user'])` override right under it now fixes plain links; `:where()` keeps it at the same weight so `a[data-nav]` and the rules below still win.

For a link that must react on both palettes, lean on the page's own foreground instead: `color-mix(in srgb, var(--color-accent) 70%, var(--color-text))` brightens the green on the dark ground and deepens the olive on the light one from one declaration.

## Give a link a hover vocabulary, never an inline colour
An inline `color` on a link outranks every selector, so no `:hover` can reach it — that is how a dozen links on the user side ended up dead. Leave colour off the element and pick an attribute from nocturne.css instead: `data-nav` for header navigation (bar + lift, pass `padding: '4px 0'` inline like top-nav.tsx), `data-inline-link` for a text link or a Link `as="button"` that reads as one (bar grows out of the baseline; do not also set an inline `background`, it erases the bar), and `data-quiet` for a link that reads as text until pointed at, such as a title in a list.

Related: the `--color-neutral-*` ramp inverts the same way under `[data-mod="user"]`.

## html keeps scrollbar-gutter: stable, or every page shifts 4px on navigation
Nocturne styles the scrollbar as a classic 8px one (`*::-webkit-scrollbar { width: 8px }`), so it takes layout width rather than floating over the page. Every shell on the site is `margin-inline: auto`, so those 8px come out of the centred width — and only on pages tall enough to scroll.

Measured at 1440px: a short screen put the shell's left edge at 60px, a tall one at 56px. The main column and the sticky header both slid 4px sideways on every navigation between the two. That is the "the page moves when I open Recruit" QA report; it was happening on every screen, not just that one.

`html { scrollbar-gutter: stable }` in app.css's base layer holds the track open always. Do not remove it to "get the 8px back" — the gutter is the price of a page that does not jump.
