---
paths:
  - app/Http/Middleware/SecurityHeaders.php
---

# Middleware

## No Content-Security-Policy header — deliberately out of scope, not an oversight
SecurityHeaders sets X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy globally, but not CSP. Reason: Alpine.js needs 'unsafe-eval' for its expression evaluation, and this environment has no way to drive a real browser to verify a CSP doesn't silently break Alpine/Livewire interactivity (curl can't execute JS). Don't add a CSP here without browser-based verification first — a wrong one fails silently. Same reasoning applies to any future tightening of these headers.
