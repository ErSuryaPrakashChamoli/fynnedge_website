---
paths:
  - 'app/Providers/Filament/AdminPanelProvider.php,resources/css/filament/admin/theme.css,vite.config.js'
---

# Filament Admin

## Admin font and primary palette are explicit for a reason
Plus Jakarta Sans is self-hosted via the bunny() entry in vite.config.js and printed by the HEAD_END hook (Vite::fonts('plus-jakarta-sans'), wrapped in try/catch) with ->font(..., provider: LocalFontProvider::class): the CSP font-src/style-src only allow 'self', so Filament's default Bunny CDN link is silently blocked. The primary palette is listed shade by shade (brand navy #1E3A5F = 800) because Color::hex('#1E3A5F') made 600 so light that Filament rendered every primary button pale blue with dark text. Content is ->maxContentWidth(Width::Full).
