---
paths:
  - 'database/migrations/**'
---

# Migrations

## Name unique/index constraints explicitly on long table names
MySQL identifiers cap at 64 chars. Laravel's auto-generated constraint name (table_col1_col2_unique) silently exceeds that once the table name itself is descriptive (e.g. lender_product_document_requirements) — the CREATE TABLE succeeds but the follow-up ALTER TABLE for the constraint fails with "Identifier name ... is too long", leaving an untracked table that has to be dropped before retrying. Always pass an explicit short name as the second arg to ->unique([...], 'short_name') / ->index() on tables with long names or multi-column keys.
