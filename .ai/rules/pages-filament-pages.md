---
paths:
  - 'app/Modules/FynnOn/**,app/Modules/Leads/**,app/Filament/Pages/LeadInbox.php,app/Filament/Pages/FynnOnSync.php,app/Filament/Pages/AdminActivity.php'
---

# Pages Filament Pages

## FynnOn LMS push: status in audit_logs, inert until configured, no PII in logs
No schema change was approved (2026-11-24), so delivery history is stored in the existing audit_logs table: one row per attempt on the lead itself, action fynnon.sent|duplicate|failed|abandoned, user_id null, changes = {event, reference, attempt, http_status, lms_reference, error, duration_ms} — never contact details. No row = not sent yet. AdminActivity filters fynnon.* out. QueueLeadsForFynnOn hooks model events (enquiry created/reopened, score check created, journey phone verified, application submitted, OTP verified — never customer_login); nothing dispatches unless services.fynnon.url+token are set. Delivered = 2xx with an acknowledgement (reference/id/lead_id/success:true) or 409; payload never carries PAN, DOB, score, report or OTP data. Lead Inbox is a read-only UNION (LeadInbox::query) gated per source by that source's own resource canAccess(); newsletter subscribers are deliberately not a lead source. Real LMS URL/token/payload contract still to be confirmed with the FynnOn team.
