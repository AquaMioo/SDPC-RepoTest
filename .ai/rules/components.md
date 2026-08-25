---
paths:
  - 'resources/js/components/**'
---

# Components

## A DialogFooter inside a Form needs its own top margin
`DialogContent` spaces its children with `gap-4`, but an Inertia `<Form>` is a single child — so a `DialogFooter` written inside the form gets no gap at all and sits flush against the field above it. Measured at 0px: the Save button's border landed exactly on the select's bottom border, which is the "Overlapping" QA raised against the Add language dialog.

Footers nested in a form carry `className="mt-4 gap-3"`; footers that are direct children of the dialog carry `gap-3` only, because `gap-4` already spaces them. If you add a dialog, check which case you are in rather than copying whichever line you saw last.
