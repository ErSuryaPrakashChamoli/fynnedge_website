---
paths:
  - 'app/Filament/Schemas/HtmlBodyEditor.php,app/Filament/Resources/**/Schemas/*Form.php'
---

# Resources Schemas

## Use HtmlBodyEditor for body fields that need raw HTML — never pair RichEditor and CodeEditor on one path
Filament's RichEditor casts its state to a TipTap doc at hydration (even when hidden), silently stripping markup TipTap has no node for (div/section/custom classes). A CodeEditor on the same state path would receive that doc, not HTML. HtmlBodyEditor::make('body') keeps the source on a separate non-saved `{field}_html` path filled from the RAW value before the cast (toggle + code editor must stay before the RichEditor in its array), opens in HTML mode when the TipTap round-trip would change the body (so saving other fields never strips it), and saves the HTML via Str::sanitizeHtml(). Used by LoanProductForm and LoanProductContentForm; `{field}_html_mode`/`{field}_html` are dehydrated(false), so RestrictsUpdateToAllowedFields allowlists need only the real field.
