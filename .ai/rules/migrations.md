---
paths:
  - 'database/migrations/**'
---

# Migrations

## Dropping a column on SQLite: foreign keys and indexes first
SQLite revalidates the whole table after a drop, so a column still referenced by a foreign key or an index fails with "error in table/index X after drop column". Call dropForeign() and dropIndex() in earlier Schema::table() statements, then dropColumn. Guard each with Schema::hasColumn so a half-applied migration can be finished rather than restarted — the failing statement is not rolled back on SQLite. Also run `composer dump-autoload` after deleting a class, or every test errors on the stale autoload map.
