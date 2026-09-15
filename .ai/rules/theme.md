---
paths:
  - 'app/Models/Banner.php,resources/views/components/site/banner-carousel.blade.php,app/Support/Theme/SiteThemeStyles.php'
---

# Theme

## Per-banner button colours layer over the site-wide banner Settings
Banner button colours resolve in three layers: per-banner fields (cta_bg_color/cta_hover_color/cta_text_color, emitted by Banner::contentStyle() as --slide-cta-* on the slide's .banner-content), then the site-wide theme_banner_button_* Settings (--banner-button-* from SiteThemeStyles), then the site accent. The carousel's static <style> uses `#hero-banner .banner-content .banner-cta` so it outranks SiteThemeStyles' `#hero-banner .banner-cta` — keep that extra class or per-banner colours silently lose. Banner::contentStyle() re-validates hex via SiteThemeStyles::hexColor() and clamps padding to MAX_CONTENT_PADDING, because history restores skip form validation. Alignment → Tailwind class maps live in the Blade view, not the enums.
