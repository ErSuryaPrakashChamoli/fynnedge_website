---
paths:
  - 'resources/views/components/site/updated-at.blade.php,resources/views/loans/**,resources/views/pages/show.blade.php,resources/views/resources/show.blade.php'
---

# Pages Views Resources

## Content pages show "Updated <date, time>" last in their content
On 2026-11-02 the user asked visitors to see when content was last changed. x-site.updated-at renders "Updated 2 Nov 2026, 11:30 AM" (font-mono text-xs text-ink-faint, clock icon). It shows India time; the <time datetime> holds the UTC ISO value. Since 2026-11-03 (user request) it is the LAST element of the page's own content container, `class="mt-12"`, after the FAQs (after the newsletter box on articles), not beside the breadcrumbs, on loans/show, show-flexi-hybrid, landing-page, pages/show and resources/show. It reads the record's own updated_at, so a rate published in Interest Rates does not move a loan page's date. A new content page should end its content with the same stamp. Pinned by ContentUpdatedAtTest.
