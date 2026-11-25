---
paths:
  - 'database/migrations/**'
---

# Migrations

## Name unique/index constraints explicitly on long table names
MySQL identifiers cap at 64 chars. Laravel's auto-generated constraint name (table_col1_col2_unique) silently exceeds that once the table name itself is descriptive (e.g. lender_product_document_requirements) — the CREATE TABLE succeeds but the follow-up ALTER TABLE for the constraint fails with "Identifier name ... is too long", leaving an untracked table that has to be dropped before retrying. Always pass an explicit short name as the second arg to ->unique([...], 'short_name') / ->index() on tables with long names or multi-column keys.

## Add the replacement unique index before dropping the old one
When a composite unique index's leading column(s) also back a foreign key (e.g. `unique([application_id, document_type_id])` where `application_id` has no other single-column index), `dropUnique()` fails with MySQL error 1553 ("needed in a foreign key constraint") because MySQL has no other index left to satisfy that FK. Fix: in the same migration, `$table->unique(...)` the new replacement index in one `Schema::table()` call, then `dropUnique()` the old one in a second call — never the reverse order. Mirror the same ordering in `down()`. Hit this in 2026_08_30_081038_add_slot_and_custom_label_to_application_documents_table.php.

## Never ->after() a column you didn't verify in the migrations
The local dev DB has drifted from production (e.g. articles.city_id exists locally but no migration creates it), so a column you see in your local schema may not exist on production MySQL. ->after('city_id') in 2026_11_24_130251 passed every test (phpunit runs SQLite, which ignores after()) and then failed `migrate --force` on production with SQLSTATE 42S22. Column position is cosmetic: leave ->after() out, or only name a column that a migration in this repo creates. Dry-run new MySQL DDL with `DB::connection()->pretend(fn () => $migration->up())` in tinker before shipping.
