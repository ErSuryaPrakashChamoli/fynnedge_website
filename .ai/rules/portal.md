---
paths:
  - 'app/Models/Concerns/Auditable.php,app/Http/Middleware/EnsureCustomerPortalAccess.php,app/Http/Controllers/Portal/**'
---

# Portal

## auth:customer switches the default guard — audit user_id must use the web guard
Laravel's Authenticate middleware calls Auth::shouldUse('customer') on /my-profile routes, so Auth::id() would return a CUSTOMER id and land in audit_logs.user_id (FK to users): FK error or a staff user wrongly credited. EnsureCustomerPortalAccess resets Auth::shouldUse('web') after its check, and Auditable reads Auth::guard('web')->id() by name. Portal code must ask for the customer by guard name ($request->user('customer')). For portal-originated events on bureau requests, write auditLogs()->create() with user_id null instead of recordEvent() (its null falls back to Auth::id()). Tests: actingAs($c, 'customer') also switches the default guard.
