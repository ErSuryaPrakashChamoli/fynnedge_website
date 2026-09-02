---
paths:
  - 'app/Support/Options/**,resources/views/components/ui/searchable-select.blade.php'
---

# Ui

## City/employer "pick from list or Other" fields: one shared list, one shared widget
The city and employer-name option lists used to live only inline inside database/seeders/JourneySeeder.php (for the DB-driven customer journey's SearchableSelect fields). They're now extracted to App\Support\Options\CityOptions::all() and App\Support\Options\EmployerOptions::all() (array<{value,label}>) so any part of the app needing the same "pick from a bundled list, or choose Other and type your own" UX can reuse the same data — JourneySeeder now just delegates to them.

For a plain (non-journey, non-DB-driven) Livewire component field with the same UX — e.g. ⚡loan-eligibility-calculator.blade.php's city/employerName — use <x-ui.searchable-select name="..." label="..." :options="..." />, a generic Blade component at resources/views/components/ui/searchable-select.blade.php. It's the Livewire-native sibling of resources/views/components/journey/field.blade.php's FieldType::SearchableSelect case: same Alpine x-data shape (open/mode/query/choose/chooseOther/backToSearch), but writes the chosen value straight to `$wire.{name}` (Livewire 4's recommended direct-property-access pattern — see livewire/livewire docs "Alpine" page) instead of a shared journeyStep() `values` object + hidden `<input name>` for native form POST. Don't reintroduce a per-field hardcoded city/employer array anywhere — always source from these two classes, and always keep an "Other — enter manually" escape hatch since the lists are convenience data, not the source of truth.
