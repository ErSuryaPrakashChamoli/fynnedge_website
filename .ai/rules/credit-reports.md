---
paths:
  - 'app/Http/Controllers/Portal/CreditReportController.php,resources/views/portal/credit-reports/**'
---

# Credit Reports

## Customer "Download PDF" is FynnEdge's own dompdf copy, English-only
User decision (2026-10-17): barryvdh/laravel-dompdf was added so every Completed genuine report can be downloaded (route portal.reports.download). There is a link on the reports list and the dashboard table, and a button on the report page. The file is built from saved data in portal/credit-reports/pdf.blade.php: plain HTML tables and CSS 2.1, with identifiers masked through CreditReportView. It never calls a bureau and is separate from pdf(), which only serves a stored bureau PDF. It is rendered with withLocale('en') because dompdf cannot join Devanagari letters. Keep isFontSubsettingEnabled on (886 KB down to 32 KB). Each download is logged as report_pdf_downloaded_by_customer on the bureau request, with no user_id. Failed, pending and demo checks get a 404. Pinned by CustomerCreditReportsTest.
