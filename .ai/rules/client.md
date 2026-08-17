---
paths:
  - app/Http/Controllers/Client/StudentProfileController.php
---

# Client

## An invitation is what makes a student messageable
Recruit → student profile → invite → message is the client-initiated path, and it only works in that order. Conversations require an applications row, so the invite (source=invited) is the introduction that unlocks messaging; calling messages.store before it 403s by design. The profile screen passes invitableProjects (the team's postings the student is not already on) and canInvite, so it never shows a dead invite button.
