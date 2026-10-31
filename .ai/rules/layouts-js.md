---
paths:
  - 'resources/views/components/layouts/app.blade.php,resources/js/app.js'
---

# Layouts Js

## livewire.js loads only on pages with a Livewire component; app.js starts Alpine elsewhere
Since 2026-10-31 the layout has no @livewireStyles/@livewireScripts: Livewire auto-injects them (livewire.inject_assets) only when a component rendered (calculators, loan pages, credit score, sign-in, portal refresh). On every other page app.js, seeing no window.Livewire, dynamic-imports alpinejs and starts it after all alpine:init listeners are registered. Never re-add @livewireScripts (it forced 254 KB of Livewire onto the homepage, contact and articles just for Alpine) and never start the standalone Alpine when window.Livewire exists (two Alpines break wire:model). Public pages use no Alpine plugin outside Livewire components; using x-collapse/x-trap/x-intersect/$persist on a non-Livewire page now needs that plugin registered in app.js. Pinned by PageScriptsTest.
