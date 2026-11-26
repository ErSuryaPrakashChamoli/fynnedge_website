---
paths:
  - resources/views/quick-enquiry.blade.php
  - 'resources/views/**'
  - resources/views/home.blade.php
  - resources/views/contact.blade.php
---

# Resources Views

## Quick Enquiry page renders the form FIRST in the DOM
Unlike the loan pages (content first, form in the right column), /quick-enquiry puts the form wrapper (#enquiry-form) before the copy in the markup so a phone visitor lands on the form, and keyboard/screen-reader order matches. `lg:order-last` moves it back to the right-hand column on desktop (grid auto-placement follows order-modified order). Breadcrumbs sit in their own row above the grid so they don't end up mid-page on mobile. The form card carries `ring-4 ring-accent/15` as its highlight. QuickEnquiryPageTest pins the order (loanEnquiryForm( before the heading text) — don't move the form back after the copy.

## Never mix inline @php(...) with a @php/@endphp block in one Blade file
Blade pairs an inline `@php($x = ...)` with a LATER `@endphp` in the same template, producing "ParseError: unexpected token endforeach/endif" at render time (every page using that component 500s). If a view needs a block `@php ... @endphp` anywhere, write every other `@php` in that file in block form too. Hit this in x-site.video-testimonials and x-site.video-testimonial-bubble.

## Body copy in full-width sections spans the container and is justified
Rich-text bodies (loan/landing/flexi bodies, calculator explainers) and section intro paragraphs inside max-w-7xl or centred max-w-3xl containers use `max-w-none` (or no max-w) plus `text-justify hyphens-auto`. Don't add `max-w-xl/2xl/3xl` to them: that left a wide blank gutter on the right. The typography plugin is NOT installed, so `prose` does nothing and the max-w class was the only width constraint. Exceptions that keep their cap: hero copy beside a form or image (home hero, loan-enquiry, flexi-hybrid-hero, quick-enquiry hero, banner), the footer disclaimer, and the home product summary next to its CTA. Pinned by tests/Feature/ContentAlignmentTest.php.

## Page containers match the header width; zoom reveals grow from the top
Every public page's outer section uses `mx-auto max-w-7xl px-6 lg:px-8`, the same as x-site.header and x-site.footer, so content lines up with the logo and the header buttons on both sides. Narrower max-w-3xl/4xl/5xl/6xl outer containers were removed site-wide on 2026-09-15 because they left extra side space compared with other pages. Constrain width only on inner elements, never on the page container. Separately, `[data-reveal^='zoom']` uses transform-origin: top center, and initScrollReveal (app.js) counts a tall element as seen once it fills 20% of the viewport. Without both, a tall block such as a calculator stays blank on load until the visitor scrolls.

## Admin-editable pills/eyebrows must never use w-max
`w-max` (width: max-content) on an element whose text comes from a Setting or MarketingSection lets a long admin string widen its whole grid/flex column past the phone viewport — the hero heading, copy and CTAs then clip at the right edge on production while the shorter local seed text looks fine. Use `max-w-full self-start` (or `w-fit max-w-full`) plus `min-w-0` on the column so the pill wraps instead. Pinned by SiteBrandingTest "wraps a long hero eyebrow". Only nav dropdowns (absolute-positioned, hardcoded content) may keep `w-max`.

## Never mix Tailwind translate-* classes with an inline transform on one element
Tailwind v4 compiles -translate-x-1/2 / -translate-y-full etc. to the separate CSS `translate` property, not `transform`. An element that also has `style="transform: translate(...)"` gets BOTH applied, so the shifts add up. This put the map pin label in x-site.location-map a full label-width left of the pin (2026-10-09). Position an element with either the utilities or the inline transform, never both. Pinned by ContactFormTest "positions the map pin label with one transform". Headless Chromium/Firefox --screenshot never paint the lazy cross-origin Google Maps iframe (it shows as a blank/grey box), so a blank map in those screenshots is not a bug. Check maps with a CDP-driven Chromium that scrolls the iframe into view and waits a few seconds.

## Contact form phone is optional but OTP-verified when filled
A blank phone submits with no code. A filled phone must be an Indian mobile (MobileNumber::PATTERN, normalised first) verified via the public API with purpose contact_enquiry; the page (Alpine contactPhoneOtp in app.js) posts only a verified otp_challenge_id. ContactController::store() re-checks findForVerification(id, phone, ContactEnquiry) + isVerified() and consumeFor() inside the same transaction that creates the ContactEnquiry with phone_verified_at — never trust a browser flag. With JavaScript off a filled phone cannot be verified, so the error tells the visitor to leave it blank. Use x-on:click (not @click) on x-ui.button in this view. Pinned by ContactFormTest.

## Nothing on a page's first screen carries data-reveal
data-reveal keeps an element at opacity 0 until app.js runs, and app.js runs only after livewire.js loads. Measured 2026-10-30, it delayed the LCP on every page by 1.3–2.3s of element render delay. /loans/personal-loan had no reveal in its hero and measured 0.25s. Keep it off a page's h1, intro, CMS/article body (.rich-text), and the main form or calculator in the first section. Keep it off the whole hero on /partners, /quick-enquiry and x-site.flexi-hybrid-hero. Reveals further down the page are fine. PageFirstScreenTest pins this for each page type. HomepageHeroBannerTest pins the homepage.

## Homepage score gauge animates only while on screen, outside Alpine state
The credit score gauge's requestAnimationFrame count-up used to run for the whole visit through x-text/:style, even scrolled out of sight: ~1.9s of the homepage's mobile main-thread work (Lighthouse, 2026-11-03). Its loop now starts/stops from an IntersectionObserver on the card, and tick() writes $refs.needle.style.transform and $refs.score.textContent directly. The server-rendered rest state is needle rotate(20deg) with 760, which is also what reduced-motion visitors see. Don't move it back to reactive Alpine state or an always-on loop. Pinned by HomepageScoreGaugeTest.

## Server-render the real value; Alpine may only animate on top of it
AI assistants and most crawlers (GPTBot, ClaudeBot, PerplexityBot…) read the raw HTML and never run JavaScript. The homepage stats strip once shipped `<span x-text="value">0</span>+` and a count-up, so chatbots quoted the site as "0+ partner banks". Any x-text/x-html that displays real content (numbers, prices, rates, product names, headings) must have the final value as its server-rendered inner text: `<span x-text="value">{{ $value }}</span>`, and x-data should start at that value. A count-up/typing effect resets to its start value inside init() only when it really animates. Skip it for prefers-reduced-motion and for `navigator.webdriver` (headless renderers). Patterns to copy: hero-stats.blade.php and credit-score-meter.blade.php. Empty x-text is fine only for transient UI state (OTP errors, resend timers, countdown ticks). Pinned by "server-renders each derived figure…" in HomepageHeroBannerTest.

## Use @use, not @php use …, for imports inside a component slot
A `@php use App\…\X as P; @endphp` block inside `<x-layouts.portal>…</x-layouts.portal>` (a component slot) compiles into a closure where PHP `use` imports are illegal: ParseError "unexpected token use", the page 500s. Put `@use('App\…\X', 'P')` at the top of the file instead. Top-level @php use blocks outside slots happen to work, but @use is always safe.

## Homepage Debt Consolidation feature: placement home_debt_consolidation, gated on hubIsLive
The section after <x-site.hero-stats> and before Quick Enquiry (data-home-debt-consolidation) renders only when HomeController::debtConsolidationFeature() is non-null, i.e. the service is on and the hub is published. Each button links only to a DC page in DebtConsolidationPages::publishedKeys(): the primary button falls back from the assessment to the hub ("Explore debt consolidation"), and the calculator button is omitted when that page is unpublished. Wording comes from the MarketingSection placement home_debt_consolidation, with the hardcoded copy as fallback: heading = first line, subheading = the accent second line, description, cta_label/cta_url. The audience line follows DebtConsolidationSettings::availableApplicantTypes(). Never word it as a promised saving. Pinned by DebtConsolidationSitePreferenceTest.

## Sticky panels and scroll targets clear the header with top-below-header
Since 2026-11-26 the top strip and the header stick together ([data-site-top] in x-site.header): about 130px on desktop and 156px on phones while Debt Consolidation is live. The header writes its measured height to --site-header-height on <html> (a ResizeObserver; app.css has 9.75rem/8.125rem fallbacks). Any element that sticks below the header uses `lg:top-below-header` (height + 1rem), and any scroll or anchor target uses `scroll-mt-below-header`. Both are @utility rules in app.css. Never use a fixed top-20/top-24 or scroll-mt-24/28: they slid under the taller block. Pinned by SiteStylesheetTest "offsets every public sticky panel…". lg:top-6 panels (quick-enquiry, loan-enquiry) predate this and were left as they were.
