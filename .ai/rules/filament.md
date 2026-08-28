---
paths:
  - 'app/Enums/**,app/Filament/**'
---

# Filament

## PHP enums used in Filament selects/badges must implement HasLabel::getLabel()
Filament's Select::options(EnumClass::class) and badge columns only render human labels if the enum implements Filament\Support\Contracts\HasLabel with a getLabel() method — not a plain custom label() method. All app enums (PublishStatus, LoanCategory, LenderStatus) follow this. Keep doing it for new enums (e.g. future ApplicationStatus).
