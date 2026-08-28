---
paths:
  - 'app/Http/Controllers/JourneyController.php,app/Modules/Journey/**'
---

# Journey

## Journey step validation must merge saved responses with this request's input
When computing which fields are visible/required for a step submission, merge $session->responsesByKey() with $request->all() before calling JourneyStepResolver::validationRulesFor() — using only the saved (pre-request) responses means a field conditional on another field answered in the SAME step (e.g. company_name shown when employment_type=salaried, both submitted together) never gets required, silently skipping it. See JourneyController::update().
