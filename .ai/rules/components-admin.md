---
paths:
  - 'resources/css/filament/admin/theme.css,resources/views/filament/**,resources/views/components/admin/**'
---

# Components Admin

## Admin hover and selected states use the shared fe tokens
User decision (2026-11-24): hover and selected must be clearly visible everywhere in the admin, never a near-white grey. theme.css defines --fe-hover-mix / --fe-selected-mix (plus ring mixes, stronger in .dark): hover = accent tinted into the surface plus a thin accent outline; selected = deeper tint plus a stronger outline or accent bar. In custom admin views, add the `fe-hover` class to clickable rows, links and cells instead of hover:bg-gray-50; set [--fe-accent:#hex] on the element for a different tone. Table row hover is `tr.fi-ta-row:hover`, because a plain `tr:hover` loses to the even-row stripe rule (specificity) and striped rows never lit up. Run `npm run build` after changes.
