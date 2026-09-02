---
paths:
  - 'resources/css/**,resources/views/**,app/Support/Theme/**,app/Filament/Pages/Settings.php'
---

# Theme Filament Pages

## Per-section appearance overrides come from Settings, via scoped CSS variable redeclaration
Admin-configurable background/font colour/size/weight/style for the header, footer and main content ("Appearance" sections on the Settings page) are stored as `theme_{header,footer,main}_*` Setting keys and rendered by App\Support\Theme\SiteThemeStyles::render() as a single scoped `<style>` block in components.layouts.app's `<head>` (via a View::composer in AppServiceProvider).

This works WITHOUT touching any Blade template's Tailwind classes because Tailwind v4 compiles every utility to reference a CSS custom property (`.text-ink { color: var(--color-ink) }`, `.text-sm { font-size: var(--text-sm) }`, etc., all defined once on `:root` from the `@theme` block in app.css). Redeclaring the same variable names scoped to `header`/`footer`/`#main-content` cascades to every descendant utility automatically — a rule declared directly on an element always wins over the inherited `:root` value, regardless of selector specificity.

Font-size/weight overrides deliberately force ALL Tailwind size/weight tiers within that section to the SAME single admin-chosen value (uniform override), not a proportional scale — this is the literal, predictable interpretation and is documented as a known trade-off (it removes typographic hierarchy within that section if used).

Every value is re-validated against a closed allow-list or strict hex regex inside SiteThemeStyles itself (never trust Setting::get() alone, even though the Filament form already constrains input) — this string is echoed unescaped into `<head>`, so this is the CSS-injection guard. When adding a new appearance field, add it to SiteThemeStyles' allow-list AND the Settings.php form — Setting::set() has no key restriction of its own.

Note: purely-numeric Select option keys (e.g. font-weight '300'..'800') come back from PHP as int, not string, because PHP auto-casts canonical-integer array keys — SiteThemeStyles::allowed() normalizes with `(string)` before comparing. Keep that normalization if this pattern is reused elsewhere.
