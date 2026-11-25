---
paths:
  - 'resources/css/filament/admin/theme.css,app/Filament/Resources/**/Tables/*.php'
---

# Resources Tables

## Admin tables: inner scroll, sticky headings, packed columns
theme.css gives .fi-ta-content-ctn a max-height (100dvh - 15rem) so rows scroll inside the table: column headings stay sticky and the sideways scrollbar is always on screen. Columns are packed at natural width and the last column (row actions) takes the spare width — unless the table has a ->grow() column. Wrapped (->wrap()) cells get min-width 16rem and descriptions wrap at 24rem, otherwise they collapse/overflow. Relationship counts: TextColumn::make('lender_products_count')->counts('lenderProducts') — the attribute is snake_case; 'lenderProducts_count' renders blank (was a live bug in LoanProducts/Lenders lists).
