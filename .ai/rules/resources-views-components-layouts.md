---
paths:
  - resources/views/components/layouts/app.blade.php
---

# Resources Views Components Layouts

## Page titles render exactly as set — no site-name suffix
Decided 2026-09-23: the layout's <title> outputs a page's own title (view prop, record SEO title or Page SEO override) verbatim, with NO " — {site_name}" / "| FynnEdge" appended, and no form rule requires the brand. Admins add the brand to a title themselves if they want it. Only a page that passes no title falls back to "{site_name} — {tagline}" (SiteBrandingTest). og:site_name still carries the brand separately.
