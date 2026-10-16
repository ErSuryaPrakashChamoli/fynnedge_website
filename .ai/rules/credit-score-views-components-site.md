---
paths:
  - 'resources/views/components/⚡credit-score-check.blade.php,app/Modules/CreditScore/**,resources/views/components/site/credit-score-*.blade.php'
---

# Credit Score Views Components Site

## Score result advice comes from report insights, never the stored report
The result step shows x-site.credit-score-meter (300–900 dial, ScoreBand colours --color-score-*) and x-site.credit-score-advice, both driven by CreditScoreAdvice(score, insights, bureau). Insights are the 11 headline ints from each bureau document's insights() (Crif/Equifax/Experian/CibilReportDocument), passed as CreditCheckResult::$insights and stored in credit_score_checks.report_insights (encrypted:array; copied on reuse; null for demo or the score-only endpoint). Keep counts/totals only — no lender names, account numbers or identity data. A new bureau provider must return the same 11 keys to get report factors and personalised tips; without them the page falls back to band-only tips. ScoreBand cut-offs: Poor <550, Average 550–649, Good 650–749, Excellent 750+.
