---
paths:
  - 'app/Models/**,app/Modules/**/Models/*.php,app/Models/Setting.php,app/Support/Localization/**,app/Filament/**'
---

# Localization Filament

## Admin content is auto-translated to Hindi on save (Google Translate)
The user asked (2026-10-15) that anything added in the CMS appears in Hindi automatically. HasTranslations models queue TranslateContentToHindi after every save; Setting::set() queues TranslateSettingToHindi (deferred to the end of the request) for keys in AutoTranslation::translatableSettingKeys(). Rule: Hindi is retranslated when blank, or when its English changed in a save that left the Hindi alone; Hindi an editor changed in the same save is never overwritten. Jobs re-check fingerprints of the STORED row, so read the row, not a possibly stale instance. Needs GOOGLE_TRANSLATE_API_KEY (blank in phpunit.xml on purpose — tests fake Http); without it nothing is queued and English shows. `translations:fill-hindi` (daily 03:30) catches up. Adding a visitor-text Setting key means adding it to translatableSettingKeys(); MachineTranslator keeps FynnEdge, lender/bureau names, [rate:…] tokens and URLs in English (user decision 2026-10-15) and maps ऋण→लोन, ऋणदाता→लेंडर.
