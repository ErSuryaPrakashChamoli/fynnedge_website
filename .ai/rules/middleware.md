---
paths:
  - app/Http/Middleware/SecurityHeaders.php
---

# Middleware

## Content-Security-Policy is live (added 2026-08-29)
SecurityHeaders sets X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, and now Content-Security-Policy globally. See `.ai/rules/providers-filament.md` for the full policy rationale and its verification limits (curl-verified server-side only — no browser automation tool exists here to confirm client-side JS behavior is unaffected).

## CSP frame-src allows Google Maps for the admin-configured contact map embed
The contact page renders an iframe from the admin-set `contact_map_url` Setting (Filament Settings page). Since default-src has no frame-src fallback exception, framing any origin needs an explicit frame-src directive — SecurityHeaders::csp() sets `frame-src 'self' https://www.google.com https://maps.google.com`. If the map embed source ever changes to a different provider (e.g. Mapbox, OpenStreetMap), that provider's domain must be added here too, or the iframe silently renders blank with a console-only CSP violation (no failed network request).
