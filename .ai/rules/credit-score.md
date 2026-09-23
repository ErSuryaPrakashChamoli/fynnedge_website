---
paths:
  - 'app/Support/Pages/CreditScorePageContent.php,app/Filament/Pages/CreditScorePageSettings.php,resources/views/credit-score/show.blade.php'
---

# Credit Score

## Credit Score page copy is one Setting PER bureau page
Hero badge/headline/intro, benefits heading+list, highlights strip and meta title/description on /credit-score/{bureau} live in one Setting per bureau, `credit_score_page.{cibil|experian|equifax|crif}` (CreditScorePageContent::settingKey()), so each page is independently editable at Admin → Website Settings → Credit Score Page (`?bureau=` picks the page; permission View:CreditScorePageSettings, granted to Marketing in RoleSeeder). A bureau never saved falls back to the legacy shared key `credit_score_page`, then to defaults — so "reset" stores `[]`, not null (null would resurrect the legacy copy). `{bureau}` in any field still becomes the bureau label via forBureau(); the admin form edits resolve($bureau) (placeholder intact). Blank text → default, saved empty list hides that block. Not editable here: the Livewire check form's steps and the BureauName list.
