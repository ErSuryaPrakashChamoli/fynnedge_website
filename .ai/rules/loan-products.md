---
paths:
  - 'app/Filament/Resources/Faqs/**,app/Filament/Resources/LoanProducts/**'
---

# Loan Products

## General FAQs vs product-attached FAQs are two different UIs over one table
faqs.faqable_id is nullable. FaqResource (top-level nav item, labelled "General FAQs") is scoped via getEloquentQuery() to whereNull('faqable_id') — it's for site-wide FAQs. Product-specific FAQs are managed through LoanProductResource's FaqsRelationManager instead, which auto-sets the morph columns. Don't remove the whereNull scope or general/product FAQs will mix in the same list.
