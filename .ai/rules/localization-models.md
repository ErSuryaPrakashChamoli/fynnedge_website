---
paths:
  - 'database/seeders/**,app/Support/Localization/**,app/Models/Setting.php'
---

# Localization Models

## Hindi for existing content ships as data files matched by exact English
database/seeders/data/translations/hi/{table}.json holds {field, en, hi} entries. ContentTranslations::apply() fills only BLANK `_hi` columns of rows whose English equals `en` exactly (so reworded content safely stays English, and admin-typed Hindi is never overwritten). It runs from migration 2026_10_14_150544 (deploys only migrate) and HindiContentSeeder (last in DatabaseSeeder). Re-running a single content seeder alone (e.g. LoanLocationPageSeeder) needs `php artisan db:seed --class=HindiContentSeeder --force` afterwards; changing a seeder's English copy means updating the matching data file entry. Settings text: read visitor text settings with Setting::localized() and whole page-copy settings via Phrasebook::localize($content, Setting::get(KEY.'_hi')) — admin Hindi override → lang/hi.json lookup of the English (covers every built-in default) → English.
