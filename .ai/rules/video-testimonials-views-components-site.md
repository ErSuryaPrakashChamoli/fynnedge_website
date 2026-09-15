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
