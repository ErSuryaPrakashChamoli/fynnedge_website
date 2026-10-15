---
paths:
  - 'resources/views/**,app/Http/Controllers/**,app/Support/**,app/Enums/**,lang/**'
---

# Enums

## Public site is bilingual (English/Hindi): every visitor string goes through __()
Since 2026-10-14 visitors pick English or हिन्दी with the header switch (x-site.language-switch → /language/{locale} → plain `site_locale` cookie; SetSiteLocale middleware on the web group). Any new visitor-visible literal in Blade/PHP/enum getLabel() must be `__('Exact English')` with its Hindi added to lang/hi.json (keys are the English text, so English output is unchanged); use named :placeholders, never concatenation, and @js(__()) inside Alpine. JS literals in resources/js/app.js go through its t() helper. Follow the glossary already used in lang/hi.json (लोन, ईएमआई, पात्रता जांचें…); brand, lender and bureau names stay in English. The admin panel always stays English — SiteLocale::appliesTo() also skips Livewire requests sent from /admin pages.
