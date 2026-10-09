---
paths:
  - 'app/Support/Contact/**'
---

# Contact

## Map links are converted at save and re-checked at render (OfficeMap)
Google's /maps/place/... pages and maps.app.goo.gl short links (which 302 there) send X-Frame-Options: SAMEORIGIN, so framing them shows Chrome's "This content is blocked". Settings::save() runs every contact_map_url through OfficeMap::embedUrlFrom(). It takes pasted <iframe> code (keeps the src), short links (resolved once via Http::withoutRedirecting), place links (→ maps?q=<name>&ll=<!3d,!4d coords>&z=17&output=embed, which still shows the Google listing card), and q= links (→ maps.google.com + output=embed). It returns null → form error for anything else. Render sites (ContactController, footer) read OfficeMap::embedUrl(), which hides a stored value that fails isEmbeddable() (https + www/maps.google.com + /maps/embed or output=embed, matching CSP frame-src). Tested in Chromium: `ftid=` shows a world map and `q=Name@lat,lng` gives a bare pin, so don't use either. OfficeMap::viewUrl() is non-null ONLY for a bare coordinate q. x-site.location-map draws its label + click-catcher only then, because a listing map names its own pin.
