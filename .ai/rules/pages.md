---
paths:
  - 'app/Filament/Pages/**'
---

# Pages

## Livewire public properties must stay plain — never store DTOs/enums/models mixed in an array
A Filament Page's public property holding a Collection of ['model' => EloquentModel, 'dto' => SomeReadonlyDtoWithEnums] throws "Property type not supported in Livewire" — Livewire can synth plain arrays, primitives, and Eloquent models/collections on their own, but not arbitrary objects (readonly DTOs, enums) nested inside an array. Convert to a plain array (enum->value, pull only needed scalar fields off models) before assigning to a public property. See EligibilityTester::evaluate().
