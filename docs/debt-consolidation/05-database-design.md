# 05 — Database design

Generated from the local MySQL schema after `php artisan migrate` (25 Nov 2026). All 18 tables are new; no existing table or column was changed. Foreign keys into existing tables (`customers`, `contact_enquiries`, `users`, `lenders`, `lender_products`, `document_types`) are nullable with `ON DELETE SET NULL`, except `debt_lender_policies.lender_product_id` / `debt_lender_matches.lender_product_id` (cascade: a policy cannot outlive its offer) — so nothing here can block or alter an existing record.

## Migrations

| Migration | Creates | Reversible |
| --- | --- | --- |
| `2026_11_25_144700_create_debt_applications_tables` | applications, financial profiles, liabilities, collaterals | drop |
| `2026_11_25_144702_create_debt_records_tables` | documents, consents, notes, status changes | drop |
| `2026_11_25_144703_create_debt_calculation_tables` | scoring configs, snapshots, scenarios, quotations, reports | drop |
| `2026_11_25_144705_create_debt_lender_tables` | lender policies, matches, lender applications | drop |
| `2026_11_25_144706_create_debt_closure_tables` | closures, closure payments, documents→closures FK | drop |
| `2026_11_25_145413_seed_debt_consolidation_reference_data` | 8 document types, scoring config v1 × 2, Debt Advisor role/permissions | removes unused document types |
| `2026_11_25_150300_create_debt_consolidation_pages` | 7 draft `pages` rows + SEO + 25 draft FAQs | removes rows still unedited drafts |

Rollback was verified on MySQL: `migrate:rollback --step=7` returned the schema to the baseline table count (85) and the re-run migrated cleanly.

## `debt_applications`

The case (enquiry → application → completion). Reference DC-XXXXXXXX. FynnEdge status, assignment, follow-up, attribution.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `reference` | varchar(20) | NO |  | UNI |
| `customer_id` | bigint unsigned | YES |  | MUL → customers |
| `contact_enquiry_id` | bigint unsigned | YES |  | MUL → contact_enquiries |
| `applicant_type` | varchar(20) | NO |  |  |
| `status` | varchar(40) | NO | draft | MUL |
| `journey_step` | varchar(40) | YES |  |  |
| `full_name` | varchar(150) | YES |  |  |
| `email` | varchar(191) | YES |  |  |
| `mobile` | varchar(10) | NO |  | MUL |
| `mobile_verified_at` | timestamp | YES |  |  |
| `city` | varchar(100) | YES |  |  |
| `state` | varchar(100) | YES |  |  |
| `pincode` | varchar(6) | YES |  |  |
| `declared_total_outstanding` | decimal(16,2) | YES |  |  |
| `declared_monthly_emi` | decimal(14,2) | YES |  |  |
| `source_url` | varchar(255) | YES |  |  |
| `utm_source` | varchar(100) | YES |  |  |
| `utm_medium` | varchar(100) | YES |  |  |
| `utm_campaign` | varchar(100) | YES |  |  |
| `assigned_to` | bigint unsigned | YES |  | MUL → users |
| `follow_up_at` | timestamp | YES |  |  |
| `enquiry_submitted_at` | timestamp | YES |  |  |
| `submitted_at` | timestamp | YES |  |  |
| `last_customer_activity_at` | timestamp | YES |  |  |
| `closed_at` | timestamp | YES |  |  |
| `closure_reason` | varchar(255) | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |
| `deleted_at` | timestamp | YES |  |  |

## `debt_calculation_snapshots`

Immutable calculation records: inputs, results, formula and scoring versions, input hash (model-enforced: no column changes; a calculator snapshot is linked to a case once; only anonymous calculator snapshots are deleted, by retention).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | YES |  | MUL → debt_applications |
| `kind` | varchar(40) | NO |  |  |
| `formula_version` | varchar(20) | NO |  |  |
| `scoring_config_id` | bigint unsigned | YES |  | MUL → debt_scoring_configs |
| `scoring_version` | varchar(20) | YES |  |  |
| `inputs` | json | NO |  |  |
| `inputs_hash` | char(64) | NO |  |  |
| `results` | json | NO |  |  |
| `total_outstanding` | decimal(16,2) | YES |  |  |
| `net_benefit` | decimal(16,2) | YES |  |  |
| `suitability_score` | decimal(5,2) | YES |  |  |
| `suitability_band` | varchar(30) | YES |  |  |
| `created_by_type` | varchar(10) | NO |  |  |
| `created_by` | bigint unsigned | YES |  | MUL → users |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP |  |

## `debt_closure_payments`

Payments towards a closure with evidence and confirmation.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_closure_id` | bigint unsigned | NO |  | MUL → debt_closures |
| `amount` | decimal(14,2) | NO |  |  |
| `paid_on` | date | NO |  |  |
| `payment_mode` | varchar(20) | NO |  |  |
| `paid_by` | varchar(20) | NO |  |  |
| `utr_reference` | varchar(60) | YES |  |  |
| `debt_document_id` | bigint unsigned | YES |  | MUL → debt_documents |
| `confirmed_at` | timestamp | YES |  |  |
| `confirmed_by` | bigint unsigned | YES |  | MUL → users |
| `notes` | text | YES |  |  |
| `recorded_by` | bigint unsigned | YES |  | MUL → users |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_closures`

Closure tracking per liability (full/partial) up to verified closure.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `debt_liability_id` | bigint unsigned | NO |  | UNI → debt_liabilities |
| `debt_lender_application_id` | bigint unsigned | YES |  | MUL → debt_lender_applications |
| `closure_type` | varchar(10) | NO |  |  |
| `status` | varchar(30) | NO | planned |  |
| `debt_foreclosure_quotation_id` | bigint unsigned | YES |  | MUL → debt_foreclosure_quotations |
| `final_closure_amount` | decimal(14,2) | YES |  |  |
| `payment_initiated_at` | timestamp | YES |  |  |
| `payment_confirmed_at` | timestamp | YES |  |  |
| `closure_requested_at` | timestamp | YES |  |  |
| `noc_received_at` | timestamp | YES |  |  |
| `noc_document_id` | bigint unsigned | YES |  | MUL → debt_documents |
| `verified_by` | bigint unsigned | YES |  | MUL → users |
| `verified_at` | timestamp | YES |  |  |
| `notes` | text | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_collaterals`

Assets offered as security.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `collateral_type` | varchar(30) | NO |  |  |
| `description` | varchar(255) | YES |  |  |
| `owner_name` | varchar(150) | YES |  |  |
| `ownership` | varchar(20) | YES |  |  |
| `estimated_value` | decimal(16,2) | YES |  |  |
| `existing_charge_amount` | decimal(16,2) | NO | 0.00 |  |
| `city` | varchar(100) | YES |  |  |
| `pincode` | varchar(6) | YES |  |  |
| `verification_status` | varchar(20) | NO | unverified |  |
| `verified_by` | bigint unsigned | YES |  | MUL → users |
| `verified_at` | timestamp | YES |  |  |
| `notes` | text | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_consents`

Purpose-specific consents with exact wording, version and hash; revocation recorded, never edited.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `customer_id` | bigint unsigned | YES |  | MUL → customers |
| `purpose` | varchar(40) | NO |  |  |
| `lender_product_id` | bigint unsigned | YES |  | MUL → lender_products |
| `consent_text` | text | NO |  |  |
| `text_version` | varchar(20) | NO |  |  |
| `text_hash` | char(64) | NO |  |  |
| `method` | varchar(30) | NO |  |  |
| `granted_at` | timestamp | NO |  |  |
| `ip_address` | varchar(45) | YES |  |  |
| `user_agent` | varchar(255) | YES |  |  |
| `recorded_by` | bigint unsigned | YES |  | MUL → users |
| `revoked_at` | timestamp | YES |  |  |
| `revocation_reason` | varchar(255) | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_documents`

Uploaded files: encrypted on the private disk, SHA-256, versioned (supersedes_id).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `debt_liability_id` | bigint unsigned | YES |  | MUL → debt_liabilities |
| `debt_closure_id` | bigint unsigned | YES |  | MUL → debt_closures |
| `document_type_id` | bigint unsigned | YES |  | MUL → document_types |
| `label` | varchar(150) | YES |  |  |
| `disk` | varchar(20) | NO |  |  |
| `path` | varchar(255) | NO |  |  |
| `original_filename` | varchar(255) | NO |  |  |
| `mime_type` | varchar(100) | NO |  |  |
| `size` | int unsigned | NO |  |  |
| `sha256` | char(64) | NO |  |  |
| `is_encrypted` | tinyint(1) | NO | 1 |  |
| `version` | smallint unsigned | NO | 1 |  |
| `supersedes_id` | bigint unsigned | YES |  | MUL → debt_documents |
| `status` | varchar(20) | NO | uploaded |  |
| `rejection_reason` | varchar(255) | YES |  |  |
| `uploaded_by_type` | varchar(10) | NO |  |  |
| `uploaded_by` | bigint unsigned | YES |  | MUL → users |
| `verified_by` | bigint unsigned | YES |  | MUL → users |
| `verified_at` | timestamp | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |
| `deleted_at` | timestamp | YES |  |  |

## `debt_financial_profiles`

1:1 income/employment (individual) or cash-flow/business details.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `debt_application_id` | bigint unsigned | NO |  | UNI → debt_applications |
| `employment_type` | varchar(30) | YES |  |  |
| `employer_name` | varchar(191) | YES |  |  |
| `occupation` | varchar(120) | YES |  |  |
| `date_of_birth` | date | YES |  |  |
| `net_monthly_income` | decimal(14,2) | YES |  |  |
| `other_monthly_income` | decimal(14,2) | NO | 0.00 |  |
| `monthly_living_expenses` | decimal(14,2) | YES |  |  |
| `other_monthly_obligations` | decimal(14,2) | NO | 0.00 |  |
| `residence_type` | varchar(20) | YES |  |  |
| `declared_credit_score` | smallint unsigned | YES |  |  |
| `business_name` | varchar(191) | YES |  |  |
| `business_constitution` | varchar(30) | YES |  |  |
| `industry` | varchar(120) | YES |  |  |
| `business_vintage_months` | smallint unsigned | YES |  |  |
| `annual_turnover` | decimal(16,2) | YES |  |  |
| `average_monthly_revenue` | decimal(14,2) | YES |  |  |
| `monthly_operating_expenses` | decimal(14,2) | YES |  |  |
| `annual_net_profit` | decimal(16,2) | YES |  |  |
| `gst_registered` | tinyint(1) | YES |  |  |
| `gstin` | varchar(15) | YES |  |  |
| `udyam_registered` | tinyint(1) | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_foreclosure_quotations`

Lenders’ foreclosure quotations with validity and verification.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_liability_id` | bigint unsigned | NO |  | MUL → debt_liabilities |
| `quoted_on` | date | YES |  |  |
| `valid_until` | date | YES |  |  |
| `principal_outstanding` | decimal(14,2) | YES |  |  |
| `accrued_interest` | decimal(14,2) | YES |  |  |
| `foreclosure_charges` | decimal(14,2) | YES |  |  |
| `tax_amount` | decimal(14,2) | YES |  |  |
| `overdue_amount` | decimal(14,2) | YES |  |  |
| `other_charges` | decimal(14,2) | YES |  |  |
| `total_amount` | decimal(14,2) | NO |  |  |
| `lender_reference` | varchar(80) | YES |  |  |
| `debt_document_id` | bigint unsigned | YES |  | MUL → debt_documents |
| `verification_status` | varchar(20) | NO | unverified |  |
| `verified_by` | bigint unsigned | YES |  | MUL → users |
| `verified_at` | timestamp | YES |  |  |
| `notes` | text | YES |  |  |
| `recorded_by` | bigint unsigned | YES |  | MUL → users |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_lender_applications`

Applications facilitated to lenders with the lender’s own status.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `lender_product_id` | bigint unsigned | YES |  | MUL → lender_products |
| `lender_name` | varchar(150) | NO |  |  |
| `product_name` | varchar(150) | NO |  |  |
| `debt_lender_match_id` | bigint unsigned | YES |  | MUL → debt_lender_matches |
| `debt_consent_id` | bigint unsigned | YES |  | MUL → debt_consents |
| `status` | varchar(40) | NO | not_submitted |  |
| `lender_reference` | varchar(100) | YES |  |  |
| `applied_amount` | decimal(16,2) | YES |  |  |
| `sanctioned_amount` | decimal(16,2) | YES |  |  |
| `sanctioned_rate` | decimal(7,4) | YES |  |  |
| `sanctioned_tenure_months` | smallint unsigned | YES |  |  |
| `processing_fee_amount` | decimal(14,2) | YES |  |  |
| `disbursed_amount` | decimal(16,2) | YES |  |  |
| `submitted_at` | timestamp | YES |  |  |
| `decision_at` | timestamp | YES |  |  |
| `sanctioned_at` | timestamp | YES |  |  |
| `disbursed_at` | timestamp | YES |  |  |
| `rejection_reason` | text | YES |  |  |
| `notes` | text | YES |  |  |
| `created_by` | bigint unsigned | YES |  | MUL → users |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_lender_matches`

Matching result per case × lender offer, reasons, missing information, advisor review.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `debt_lender_policy_id` | bigint unsigned | YES |  | MUL → debt_lender_policies |
| `lender_product_id` | bigint unsigned | NO |  | MUL → lender_products |
| `debt_calculation_snapshot_id` | bigint unsigned | YES |  | MUL → debt_calculation_snapshots |
| `match_status` | varchar(30) | NO |  |  |
| `indicative_loan_amount` | decimal(16,2) | YES |  |  |
| `indicative_rate_min` | decimal(7,4) | YES |  |  |
| `indicative_rate_max` | decimal(7,4) | YES |  |  |
| `reasons` | json | NO |  |  |
| `missing_information` | json | YES |  |  |
| `eligibility_status` | varchar(30) | YES |  |  |
| `advisor_status` | varchar(20) | NO | pending |  |
| `advisor_note` | text | YES |  |  |
| `reviewed_by` | bigint unsigned | YES |  | MUL → users |
| `reviewed_at` | timestamp | YES |  |  |
| `evaluated_at` | timestamp | NO |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_lender_policies`

DC criteria per existing lender offer, versioned, effective-dated, sourced, approved.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `lender_product_id` | bigint unsigned | NO |  | MUL → lender_products |
| `version` | smallint unsigned | NO |  |  |
| `status` | varchar(20) | NO | draft |  |
| `financing_route` | varchar(40) | NO |  |  |
| `applicant_types` | json | NO |  |  |
| `accepted_liability_types` | json | NO |  |  |
| `excluded_existing_lender_ids` | json | YES |  |  |
| `accepts_own_loans` | tinyint(1) | NO | 1 |  |
| `min_loan_amount` | decimal(16,2) | YES |  |  |
| `max_loan_amount` | decimal(16,2) | YES |  |  |
| `min_monthly_income` | decimal(14,2) | YES |  |  |
| `min_business_vintage_months` | smallint unsigned | YES |  |  |
| `min_annual_turnover` | decimal(16,2) | YES |  |  |
| `min_credit_score` | smallint unsigned | YES |  |  |
| `max_foir_percent` | decimal(5,2) | YES |  |  |
| `min_dscr` | decimal(5,2) | YES |  |  |
| `max_days_past_due` | smallint unsigned | YES |  |  |
| `requires_collateral` | tinyint(1) | NO | 0 |  |
| `accepted_collateral_types` | json | YES |  |  |
| `max_ltv_percent` | decimal(5,2) | YES |  |  |
| `interest_rate_min` | decimal(7,4) | YES |  |  |
| `interest_rate_max` | decimal(7,4) | YES |  |  |
| `min_tenure_months` | smallint unsigned | YES |  |  |
| `max_tenure_months` | smallint unsigned | YES |  |  |
| `processing_fee_percent` | decimal(7,4) | YES |  |  |
| `foreclosure_restrictions` | text | YES |  |  |
| `required_document_type_ids` | json | YES |  |  |
| `source_name` | varchar(150) | NO |  |  |
| `source_url` | varchar(255) | YES |  |  |
| `source_reference` | varchar(150) | YES |  |  |
| `source_received_on` | date | YES |  |  |
| `effective_from` | date | NO |  |  |
| `effective_until` | date | YES |  |  |
| `notes` | text | YES |  |  |
| `created_by` | bigint unsigned | YES |  | MUL → users |
| `approved_by` | bigint unsigned | YES |  | MUL → users |
| `approved_at` | timestamp | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |
| `deleted_at` | timestamp | YES |  |  |

## `debt_liabilities`

Each existing debt with figures, standing, closure costs and consolidation mode. account_reference is encrypted; only the masked form is listed.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `liability_type` | varchar(40) | NO |  |  |
| `lender_id` | bigint unsigned | YES |  | MUL → lenders |
| `lender_name` | varchar(150) | NO |  |  |
| `account_reference` | text | YES |  |  |
| `account_reference_masked` | varchar(24) | YES |  |  |
| `is_secured` | tinyint(1) | NO | 0 |  |
| `repayment_structure` | varchar(20) | NO |  |  |
| `outstanding_principal` | decimal(14,2) | NO |  |  |
| `interest_rate` | decimal(7,4) | NO | 0.0000 |  |
| `rate_basis` | varchar(10) | NO | reducing |  |
| `monthly_payment` | decimal(14,2) | YES |  |  |
| `remaining_months` | smallint unsigned | YES |  |  |
| `account_status` | varchar(30) | NO | regular |  |
| `days_past_due` | smallint unsigned | NO | 0 |  |
| `overdue_amount` | decimal(14,2) | NO | 0.00 |  |
| `pending_instalment` | decimal(14,2) | NO | 0.00 |  |
| `accrued_interest` | decimal(14,2) | NO | 0.00 |  |
| `foreclosure_charge_percent` | decimal(7,4) | NO | 0.0000 |  |
| `foreclosure_charge_fixed` | decimal(14,2) | NO | 0.00 |  |
| `other_charges` | decimal(14,2) | NO | 0.00 |  |
| `lock_in_ends_on` | date | YES |  |  |
| `consolidation_mode` | varchar(10) | NO | full |  |
| `partial_amount` | decimal(14,2) | YES |  |  |
| `notes` | text | YES |  |  |
| `verification_status` | varchar(20) | NO | unverified |  |
| `verified_by` | bigint unsigned | YES |  | MUL → users |
| `verified_at` | timestamp | YES |  |  |
| `sort_order` | smallint unsigned | NO | 0 |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_notes`

Communication log, internal remarks, follow-ups (customer-visible only when flagged).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `user_id` | bigint unsigned | YES |  | MUL → users |
| `type` | varchar(30) | NO |  |  |
| `direction` | varchar(10) | YES |  |  |
| `subject` | varchar(150) | YES |  |  |
| `body` | text | NO |  |  |
| `follow_up_at` | timestamp | YES |  | MUL |
| `completed_at` | timestamp | YES |  |  |
| `visible_to_customer` | tinyint(1) | NO | 0 |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_reports`

Generated PDFs (encrypted), type, version, snapshot, SHA-256, customer visibility.

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `report_type` | varchar(40) | NO |  |  |
| `version` | smallint unsigned | NO |  |  |
| `debt_calculation_snapshot_id` | bigint unsigned | YES |  | MUL → debt_calculation_snapshots |
| `formula_version` | varchar(20) | YES |  |  |
| `disk` | varchar(20) | NO |  |  |
| `path` | varchar(255) | NO |  |  |
| `sha256` | char(64) | NO |  |  |
| `size` | int unsigned | NO |  |  |
| `is_customer_visible` | tinyint(1) | NO | 0 |  |
| `generated_by` | bigint unsigned | YES |  | MUL → users |
| `generated_at` | timestamp | NO |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_scenarios`

Proposed consolidation loans to compare (inputs).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `debt_application_id` | bigint unsigned | NO |  | MUL → debt_applications |
| `label` | varchar(120) | NO |  |  |
| `financing_route` | varchar(40) | YES |  |  |
| `lender_product_id` | bigint unsigned | YES |  | MUL → lender_products |
| `annual_interest_rate` | decimal(7,4) | NO |  |  |
| `tenure_months` | smallint unsigned | NO |  |  |
| `processing_fee_percent` | decimal(7,4) | NO | 0.0000 |  |
| `processing_fee_fixed` | decimal(14,2) | NO | 0.00 |  |
| `other_upfront_costs` | decimal(14,2) | NO | 0.00 |  |
| `fees_deducted_from_disbursal` | tinyint(1) | NO | 1 |  |
| `closure_costs_financed` | tinyint(1) | NO | 1 |  |
| `loan_amount_override` | decimal(16,2) | YES |  |  |
| `liability_overrides` | json | YES |  |  |
| `is_preferred` | tinyint(1) | NO | 0 |  |
| `created_by_type` | varchar(10) | NO |  |  |
| `created_by` | bigint unsigned | YES |  | MUL → users |
| `sort_order` | smallint unsigned | NO | 0 |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_scoring_configs`

Versioned suitability weights/thresholds per applicant type (draft → active → retired).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `public_id` | char(36) | NO |  | UNI |
| `applicant_type` | varchar(20) | NO |  | MUL |
| `version` | varchar(20) | NO |  |  |
| `status` | varchar(20) | NO | draft |  |
| `weights` | json | NO |  |  |
| `thresholds` | json | NO |  |  |
| `notes` | text | YES |  |  |
| `created_by` | bigint unsigned | YES |  | MUL → users |
| `activated_by` | bigint unsigned | YES |  | MUL → users |
| `activated_at` | timestamp | YES |  |  |
| `retired_at` | timestamp | YES |  |  |
| `created_at` | timestamp | YES |  |  |
| `updated_at` | timestamp | YES |  |  |

## `debt_status_changes`

Insert-only status history for cases, lender applications and closures (morph; model refuses updates and deletes).

| Column | Type | Null | Default | Key / FK |
| --- | --- | --- | --- | --- |
| `id` | bigint unsigned | NO |  | PRI |
| `subject_type` | varchar(255) | NO |  | MUL |
| `subject_id` | bigint unsigned | NO |  |  |
| `from_status` | varchar(40) | YES |  |  |
| `to_status` | varchar(40) | NO |  |  |
| `note` | text | YES |  |  |
| `actor_type` | varchar(10) | NO |  |  |
| `user_id` | bigint unsigned | YES |  | MUL → users |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP |  |
