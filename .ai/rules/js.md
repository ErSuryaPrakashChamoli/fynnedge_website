---
paths:
  - 'resources/views/components/⚡*.blade.php,resources/js/app.js'
---

# Js

## Calculator typed boxes use calculatorInput, never wire:model.live
The number/amount box paired with each calculator slider is driven by the `calculatorInput(property, { currency })` Alpine helper in resources/js/app.js, with limits in data-min/data-max (no wire:ignore.self, so morph keeps them current). Do NOT bind it with wire:model.live(.debounce): half-typed values ("7" on the way to 700000) got clamped to the minimum server-side and written back mid-typing, so visitors could never enter a larger figure. Sliders keep wire:model.live.
Separately, an emptied box sends "", which Livewire can't store in a typed float/int, so it unsets the property. Reading it then throws PropertyNotFoundException. Every calculator's updated() reads `$this->{$property} ?? null` and treats null as below the minimum. Keep that when adding fields. Pinned by the "emptied field" tests in each *CalculatorComponentTest.
