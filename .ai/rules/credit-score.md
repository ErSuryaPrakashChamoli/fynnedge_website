---
paths:
  - 'app/Support/Pages/CreditScorePageContent.php,app/Filament/Pages/CreditScorePageSettings.php,resources/views/credit-score/show.blade.php'
---

# Credit Score

## Credit Score page copy is one Setting shared by all four bureau pages
Hero badge/headline/intro, benefits heading+list, highlights strip and meta title/description on /credit-score/{bureau} live in ONE Setting key `credit_score_page`, resolved by App\Support\Pages\CreditScorePageContent and edited at Admin → Website Settings → Credit Score Page (permission View:CreditScorePageSettings, granted to Marketing in RoleSeeder). `{bureau}` in any field becomes the bureau label via forBureau(); the admin form edits resolve() (placeholder intact). Same fallback contract as AboutPageContent: blank text → default, saved empty list hides that block, reset stores null. Not editable here: the Livewire check form's steps and the BureauName list.
