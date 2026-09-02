---
paths:
  - 'app/Support/Loans/LoanMegaMenu.php,app/Support/Calculators/CalculatorCatalog.php,app/Models/NavigationLink.php'
---

# Calculators Models

## Do not fold LoanMegaMenu/CalculatorCatalog into NavigationLink
Audited explicitly in Phase 5.2 and deliberately left alone. `LoanMegaMenu::categories()` already derives its content live from published `LoanProduct` + `LoanLandingPage` records (only shows a category with a real, working journey — prevents dead-end "Apply" links), so it's already reactive to admin actions without being a generic link editor. `CalculatorCatalog::groups()` is 1:1 tied to real controller/route implementations — a generic NavigationLink entry could "add" a calculator link with nothing behind it. `NavigationLink` stays purely additive (footer "Quick Links" only, `location='footer'`) — never repurpose it for the header mega-menus or footer Company/Legal columns. See tests/Feature/NavigationArchitectureTest.php for the regression protection backing this decision.
