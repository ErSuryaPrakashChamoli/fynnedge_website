---
paths:
  - 'resources/views/emails/**,resources/views/components/emails/**'
---

# Emails

## Mail::fake() does not render templates — test emails with ->render()
Mail::fake() records that a mailable was queued but never executes its Blade view, so a broken email template passes the whole suite and fails only when the queue worker tries to send it in production. tests/Feature/Newsletter/EmailRenderingTest.php renders every mailable for real; add any new mailable to it.

That caught a genuine bug: Blade does NOT compile a directive written straight after a word character (`you@else`), because its directive regex requires \B before the @. The @if was silently left unterminated and the compiled view was a PHP parse error. Keep whitespace before every directive in email templates.

Email HTML rules here: table layout, inline styles only, no JavaScript, no external CSS and no web fonts — mail clients drop all four. Colours are hardcoded hex rather than the site's CSS custom properties because var() is unsupported in most clients; keep them in step with the light theme in resources/css/app.css. The unsubscribe link is rendered ONLY by components/emails/newsletter/layout.blade.php, so no template or campaign body can ship an email without one.
