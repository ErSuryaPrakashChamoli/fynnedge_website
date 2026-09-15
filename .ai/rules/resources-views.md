---
paths:
  - resources/views/quick-enquiry.blade.php
  - 'resources/views/**'
---

# Resources Views

## Quick Enquiry page renders the form FIRST in the DOM
Unlike the loan pages (content first, form in the right column), /quick-enquiry puts the form wrapper (#enquiry-form) before the copy in the markup so a phone visitor lands on the form, and keyboard/screen-reader order matches. `lg:order-last` moves it back to the right-hand column on desktop (grid auto-placement follows order-modified order). Breadcrumbs sit in their own row above the grid so they don't end up mid-page on mobile. The form card carries `ring-4 ring-accent/15` as its highlight. QuickEnquiryPageTest pins the order (loanEnquiryForm( before the heading text) — don't move the form back after the copy.

## Never mix inline @php(...) with a @php/@endphp block in one Blade file
Blade pairs an inline `@php($x = ...)` with a LATER `@endphp` in the same template, producing "ParseError: unexpected token endforeach/endif" at render time (every page using that component 500s). If a view needs a block `@php ... @endphp` anywhere, write every other `@php` in that file in block form too. Hit this in x-site.video-testimonials and x-site.video-testimonial-bubble.
