---
paths:
  - 'app/Filament/Resources/**/Tables/*.php'
---

# Tables

## A toggleable column can stay invisible for existing admins — and assertTableColumnVisible won't catch it
Filament persists each user's whole column layout in the session (Concerns\HasColumnManager, key tables.{md5(ListPage::class)}_columns). isTableColumnToggledHidden() reads that stored array and treats a column that is ABSENT or false as hidden, so changing isToggledHiddenByDefault or adding a new ->toggleable() column has no effect for any admin who already opened that list — they keep the old layout until they hit Reset in the column manager. Hit this on ArticlesTable (Created/Expires added but not showing). Fix: for a column that must always render, omit ->toggleable() entirely — Column::isToggledHidden() short-circuits to false for non-toggleable columns and skips the session lookup. Also: assertTableColumnVisible()/Hidden() only assert the column's own hidden() condition, NOT the toggle state, so they pass for a column the column manager has toggled off. To assert what an admin really sees, use array_keys($component->instance()->getTable()->getVisibleColumns()). Hiding a column does not break its ->searchable() — search iterates getColumns(), not getVisibleColumns().
