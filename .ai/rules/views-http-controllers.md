---
paths:
  - 'resources/views/about.blade.php,app/Http/Controllers/AboutController.php'
---

# Views Http Controllers

## About page bypasses pages/show; only title, excerpt, body and SEO are admin-editable
/about has its own controller and view rather than pages/show.blade.php. The view reads only title, excerpt, body (rendered in a `.rich-text` block after the excerpt, added 2026-09-19 because admin Body edits were silently ignored) and SEO fields from the `about` Page record. The founder message, mission/vision cards, "Life at FynnEdge" values and "Work with us" CTA are hardcoded in the template; founder name/photo come from Settings, and company photos from CompanyPhoto. If an admin reports an About edit "not showing", check whether that text is one of the hardcoded blocks before suspecting caching (there is no page cache).
