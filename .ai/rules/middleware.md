---
paths:
  - app/Http/Middleware/SecurityHeaders.php
---

# Middleware

## Content-Security-Policy is live (added 2026-08-29)
SecurityHeaders sets X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, and now Content-Security-Policy globally. See `.ai/rules/providers-filament.md` for the full policy rationale and its verification limits (curl-verified server-side only — no browser automation tool exists here to confirm client-side JS behavior is unaffected).
