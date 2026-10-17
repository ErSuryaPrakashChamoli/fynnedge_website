---
paths:
  - 'app/Filament/Pages/**'
  - app/Filament/Pages/CibilSettings.php
---

# Pages

## Livewire public properties must stay plain — never store DTOs/enums/models mixed in an array
A Filament Page's public property holding a Collection of ['model' => EloquentModel, 'dto' => SomeReadonlyDtoWithEnums] throws "Property type not supported in Livewire" — Livewire can synth plain arrays, primitives, and Eloquent models/collections on their own, but not arbitrary objects (readonly DTOs, enums) nested inside an array. Convert to a plain array (enum->value, pull only needed scalar fields off models) before assigning to a public property. See EligibilityTester::evaluate().

## Do not rewrite `contact_map_url`'s `q` to `Name@lat,lng` — it breaks the embed
Tried labelling the red pin by rewriting a coordinate `q` param to `FynnEdge Advisory@lat,lng` (the classic Maps "label@coordinates" trick). It does not work in the keyless `output=embed` iframe: Google treats it as a Place Details lookup, finds no matching Place ID, and the embed shows "Place info couldn't load" (console: `PLACES_GET_PLACE: NOT_FOUND`). There is no Google Business listing at the office, so this can't be fixed by adjusting the string further. The company name label on the contact page map is instead a plain HTML overlay positioned over the iframe in `contact.blade.php`, not anything encoded into the Maps URL.

## CIBIL Settings page wording follows the token check, never contradicts it
The "Free CIBIL Score page" description is built from the same CibilSettings::isConfigured() check as the token callout at the top, across four states: token set + source automatic (answered by CIBIL); token set + CIBIL_PUBLIC_SCORE_SOURCE=experian (says the token IS configured and the env var forces Experian; remove it or set "cibil" and clear the config cache); no token + automatic (switches to CIBIL by itself once SUREPASS_CIBIL_TOKEN is set); no token + forced. Before 2026-10-16 it said "CIBIL API not connected" under a "token is configured" banner. Pinned by CibilAdminTest "describes the Free CIBIL Score page without contradicting the token callout".

## CIBIL requests and the Free CIBIL Score page switch are exclusive
User decision (2026-10-17): "CIBIL requests enabled" (cibil_enabled) and "Free CIBIL Score page shows real scores" (cibil_public_score_enabled) cannot both be on. Each toggle is live() and its afterStateUpdated turns the other off; save() refuses both-on with an error on cibil_public_score_enabled (settings stored before the rule). Runtime code still reads them independently: the public CIBIL path (SurepassCibilScoreProvider) never checks cibil_enabled. So once SUREPASS_CIBIL_TOKEN is set, staff/API CIBIL pulls and real CIBIL scores on the public page cannot run at the same time. Pinned by CibilAdminTest "turns the other CIBIL switch off" and "refuses to save … with both switches on".
