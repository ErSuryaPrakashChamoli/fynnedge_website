---
paths:
  - 'resources/views/components/site/header.blade.php,resources/views/components/site/language-switch.blade.php,resources/css/app.css'
---

# Site Css

## Language switch lives in a strip above the sticky header
The user asked (2026-10-14) for an English/Hindi toggle at the top right so visitors choose before exploring (since 2026-10-17 the Sign in pill takes the very corner, see below). It sits in a non-sticky strip (data-language-strip) ABOVE <header>, right-aligned to the same max-w-7xl edge, because the 1024–1279px header row has no spare width; the mobile menu repeats it at the bottom. Labels "English" / "हिन्दी" and the strip text "Language · भाषा" are deliberately not translated (each reader must recognise their own language). Hindi labels make the nav longer: after changing nav labels, re-measure header overflow at 1024px with the site_locale=hi cookie. app.css resets letter-spacing for every tracking-* utility under html:lang(hi), because tracking breaks Devanagari conjuncts.

## Top strip order: language switch, then the Sign in pill in the corner
Since 2026-10-17 the user wants the customer portal's Sign in pill in the top-right corner of the strip above the header, with the language switch to its left. Everything in the strip is right-aligned: globe + "Language · भाषा" (hidden below sm), the switch, a divider, then the pill. The label is a plain "Sign in" ("Existing Customer?" was removed on request); a signed-in customer sees "My Profile" with a green status dot. `.portal-pill` (app.css) is fixed navy with a gold avatar disc and hairline in both themes, because white text fails on the dark theme's light-blue accent. Its sweep is disabled under prefers-reduced-motion. Pinned by SiteLanguageTest.
