---
paths:
  - 'app/Models/VideoTestimonial.php,app/Support/Testimonials/**,app/Filament/Resources/VideoTestimonials/**,resources/views/components/site/video-testimonial*.blade.php'
---

# Video Testimonials Views Components Site

## Video testimonials: FAQ placement tokens, one render per page
`video_testimonials.placements` stores the same tokens as page FAQs (route name, or `route:slug` via FaqPlacements) plus `VideoTestimonials::EVERY_PAGE` ('all'). `x-layouts.app` renders `<x-site.video-testimonials />` after the slot unless the view passes `handles-video-testimonials`. The views home, loans/show, loans/show-flexi-hybrid and loans/landing-page pass it and render the section themselves, next to their written testimonials (`embedded` on the loan views). The layout also renders the floating bubble and ONE shared player modal; cards and the bubble only dispatch `open-video-testimonial` with `playerData()`.
`VideoTestimonials::forCurrentPage()` memoises on `request()->attributes`; never call it from a Livewire component (the livewire.update route loses page placements).
Upload limit: `VideoTestimonialForm::MAX_VIDEO_SIZE` (20MB) must stay ≤ the `temporary_file_upload` max in config/livewire.php and below the 25M limits in docker php.ini and nginx.
YouTube is only embedded via youtube-nocookie.com (allowed in the CSP frame-src), and only with an id validated by `youtubeIdFrom()`.
Files live in `video-testimonials/` and `video-testimonial-covers/`, never under `testimonials/`: MediaCatalog scans directories recursively and would flag them as unused, so they could be deleted.

## Video testimonials: one card per customer, video inside the card; customer photos in their own directory
x-site.video-testimonials (standalone) is a full-bleed bg-surface-2 band, edge to edge and flush against neighbouring sections — no outer margin/gap, and deliberately tight vertical padding (py-6 lg:py-8; the user rejected py-16 as too much space) — with content at max-w-7xl; only `embedded` (inside a loan article) gets the rounded panel. Content spans the full width, not centred or shrunk to fit (user requirements, 2026-09-15). Inside: a heading row plus ONE swipeable row of cards (`data-video-card`) sized so 5 fit at xl, 4 at lg, 3 at md, 2 at sm. Keep cards short. Each card stacks, in this order: the video full-width across the top (16:10), "Watch X's story", the customer photo/initial with the name to its right, then the rating (optional headline/quote after). No spotlight panel and no details-beside-video split — the user rejected both.
Hover preview plays WITH sound (user request). Browsers reject unmuted play() until the visitor has clicked/tapped/pressed a key on the page (hover and scroll don't count), so startPreview() tries unmuted and falls back to muted on rejection — never remove that fallback, or the preview silently does nothing. Clicking a card calls stopPreview() before opening the modal so two audio tracks never overlap.
Customer photos upload to `video-testimonial-photos/` on the public disk and are registered in MediaRegistry (with customer_photo_alt); never store them under `testimonials/` or `video-testimonial-covers/`.
