---
paths:
  - 'resources/views/components/site/header.blade.php,resources/views/components/site/language-switch.blade.php,resources/css/app.css'
---

# Site Css

## Language switch lives in a strip above the sticky header
The user asked (2026-10-14) for an English/Hindi toggle in the top-right corner so visitors choose before exploring. It sits in a non-sticky strip (data-language-strip) ABOVE <header>, right-aligned to the same max-w-7xl edge, because the 1024–1279px header row has no spare width; the mobile menu repeats it at the bottom. Labels "English" / "हिन्दी" and the strip text "Language · भाषा" are deliberately not translated (each reader must recognise their own language). Hindi labels make the nav longer: after changing nav labels, re-measure header overflow at 1024px with the site_locale=hi cookie. app.css resets letter-spacing for every tracking-* utility under html:lang(hi), because tracking breaks Devanagari conjuncts.
