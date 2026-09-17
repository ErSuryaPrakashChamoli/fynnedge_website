---
paths:
  - 'app/Filament/Resources/**/Schemas/*Form.php'
---

# Filament Resources Schemas

## Loan landing page forms use HtmlBodyEditor too
LoanLandingPageForm and LoanLandingPageContentForm use `...HtmlBodyEditor::make()` for `body` (same as LoanProductForm / LoanProductContentForm), so loan sub pages get the visual editor plus "Edit as HTML" mode. Don't drop back to a bare RichEditor::make('body') on either: a body with divs/custom classes would be silently stripped on the next save. Pinned by tests/Feature/LoanLandingPageBodyEditorTest.php. The public view (loans/landing-page.blade.php) already wraps the body in `.rich-text`, so no view change is needed for editor parity.
