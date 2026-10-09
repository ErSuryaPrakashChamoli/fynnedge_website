---
paths:
  - 'app/Support/Contact/**,resources/views/components/site/location-map.blade.php,resources/views/components/site/footer.blade.php,resources/views/contact.blade.php'
---

# Components Site Views

## One map component and one OfficeMap helper for contact page and footer
The admin `contact_map_url` Setting renders through x-site.location-map (iframe + pin label overlay + pin click-catcher) on both /contact and the footer's "Visit our office" card. Never inline a second copy of that markup. The pin-label/click overlay described in pages.md now lives in that component, not contact.blade.php. App\Support\Contact\OfficeMap owns viewUrl() (moved from ContactController), directionsUrl() (embed `q`, else contact_address) and the footer card data. Setting `footer_map_enabled` (Settings → Contact channels toggle) defaults to ON, so a site with a map URL shows it until switched off. The footer composer passes null on the `contact` route so /contact never shows the map twice. The footer card's primary button uses variant="contrast" because the admin footer theme can recolour --color-accent until white-on-accent becomes unreadable (same reason as the newsletter banner).
