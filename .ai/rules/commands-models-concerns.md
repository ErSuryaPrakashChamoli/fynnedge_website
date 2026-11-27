---
paths:
  - 'app/Console/Commands/RestoreContentFromAuditLogCommand.php,app/Models/Concerns/Auditable.php'
---

# Commands Models Concerns

## Production DB timestamps are stored +5:30 off app UTC
The app runs in UTC but the production MariaDB session (and local MySQL) is IST (+05:30), so TIMESTAMP columns round-trip correctly only through an IST session. A mysqldump (TIME_ZONE='+00:00') shows every timestamp 5:30 EARLIER than the app/sitemap (dump 07:39:08 = app 13:09:08 UTC). When reading a production dump or raw SQL, add 5:30; when rehearsing on a copy, use a server/session at +05:30 or audit windows shift. Confirmed 2026-11-27 against the sitemap and audit_logs.
