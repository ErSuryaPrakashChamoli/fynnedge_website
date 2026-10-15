---
paths:
  - 'app/Filament/**,database/migrations/**'
---

# Filament Migrations

## Hindi fields get more room than English: 500-char columns, 1.5× form limits
Hindi in Devanagari runs 1.2–1.5× the characters of its English (vowel signs count), so a full 255-char English line overflowed varchar(255) on the first backfill. Every `{column}_hi` string column is varchar(500) (text/json siblings mirror their English type), and every Hindi admin field's maxLength is the English limit × 1.5 rounded up to 5 (255 → 500). Shared builder: App\Filament\Schemas\HindiTranslationSection (collapsed "Hindi translation (हिन्दी)" section, never required). Restricted-role allowlists (EditLoanProductContent, EditLoanLandingPageContent) must list each `_hi` field next to its English one.
