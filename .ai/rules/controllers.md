---
paths:
  - app/Http/Controllers/HomeController.php
---

# Controllers

## Nothing on the landing page may be invented
Every figure in the stat band is counted from the database, and the testimonial wall renders only rows a client actually wrote — there is no seeded or sample testimonial anywhere in the app, and the seeder must never add one. A stat with no data source passes null and renders as a dash, which is deliberately different from 0. The hero's old "4.7 from 205 surveyed students" star row was fabricated and was removed; it comes back only when a real review table exists.
