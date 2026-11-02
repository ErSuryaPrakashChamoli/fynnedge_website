---
paths:
  - 'resources/views/components/site/updated-at.blade.php,resources/views/loans/**,resources/views/pages/show.blade.php,resources/views/resources/show.blade.php'
---

# Pages Views Resources

## Content pages show "Updated <date, time>" beside the breadcrumbs
On 2026-11-02 the user asked visitors to see when content was last changed. x-site.updated-at renders "Updated 2 Nov 2026, 11:30 AM" in the breadcrumb row's style (font-mono text-xs text-ink-faint, clock icon). It shows India time; the <time datetime> holds the UTC ISO value. It sits in a `flex flex-wrap items-center justify-between` row with x-ui.breadcrumbs on loans/show, show-flexi-hybrid, landing-page, pages/show and resources/show. It reads the record's own updated_at, so a rate published in Interest Rates does not move a loan page's date. A new content page should add the same row. Pinned by ContentUpdatedAtTest.
