---
paths:
  - 'app/Models/**,app/Modules/**/Models/*.php,database/migrations/**,app/Filament/**'
---

# Migrations Filament

## Translatable content lives in `{column}_hi` siblings served by HasTranslations
Visitor-facing columns have a nullable `{column}_hi` twin (migration 2026_10_14_145444). Models `use HasTranslations` and list them in translatableAttributes(); while Hindi is active `$model->title` returns title_hi, falling back to English when blank — views never change. Adding a new visitor-facing text column means: add its `_hi` column, fillable + translatableAttributes() (+ 'array' cast for json), and a field in the form's "Hindi translation (हिन्दी)" section. Traps: pluck()/value()/toBase()/DB::table and select([...]) lists without the `_hi` column bypass it (LoanMegaMenu selects title_hi/city_hi; RateBook reads `_hi` from its cached raw rows); code that copies a translatable attribute into another table from a public request must store getRawOriginal() English.
