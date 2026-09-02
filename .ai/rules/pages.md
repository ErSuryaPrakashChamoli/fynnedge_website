---
paths:
  - 'app/Filament/Pages/**'
---

# Pages

## Livewire public properties must stay plain — never store DTOs/enums/models mixed in an array
A Filament Page's public property holding a Collection of ['model' => EloquentModel, 'dto' => SomeReadonlyDtoWithEnums] throws "Property type not supported in Livewire" — Livewire can synth plain arrays, primitives, and Eloquent models/collections on their own, but not arbitrary objects (readonly DTOs, enums) nested inside an array. Convert to a plain array (enum->value, pull only needed scalar fields off models) before assigning to a public property. See EligibilityTester::evaluate().

## Do not rewrite `contact_map_url`'s `q` to `Name@lat,lng` — it breaks the embed
Tried labelling the red pin by rewriting a coordinate `q` param to `FynnEdge Advisory@lat,lng` (the classic Maps "label@coordinates" trick). It does not work in the keyless `output=embed` iframe: Google treats it as a Place Details lookup, finds no matching Place ID, and the embed shows "Place info couldn't load" (console: `PLACES_GET_PLACE: NOT_FOUND`). There is no Google Business listing at the office, so this can't be fixed by adjusting the string further. The company name label on the contact page map is instead a plain HTML overlay positioned over the iframe in `contact.blade.php`, not anything encoded into the Maps URL.
