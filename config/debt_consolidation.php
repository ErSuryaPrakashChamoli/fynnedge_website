<?php

/*
 * Debt Consolidation — code-reviewed configuration (docs/debt-consolidation).
 *
 * Operational switches, wording and disclaimers live in Website Settings →
 * Debt Consolidation Settings; suitability weights in Debt Consolidation →
 * Scoring configurations; lender rules in each lender's debt consolidation
 * policy. What stays here is domain configuration that changes how the
 * financial engine classifies debt and must go through review.
 */
return [

    /*
     * Which kinds of existing debt each financing route can take over IN
     * PRINCIPLE. A lender's own policy narrows this further; a liability type
     * missing from every route available to the applicant is flagged "no
     * financing route accepts this loan type".
     */
    'route_liability_types' => [
        'personal_loan' => [
            'personal_loan', 'credit_card_outstanding', 'credit_card_emi', 'consumer_durable_loan', 'bnpl',
            'education_loan', 'vehicle_loan', 'gold_loan', 'other_personal',
        ],
        'loan_against_property' => [
            'personal_loan', 'credit_card_outstanding', 'credit_card_emi', 'consumer_durable_loan', 'bnpl',
            'education_loan', 'vehicle_loan', 'gold_loan', 'loan_against_property', 'other_personal',
            'unsecured_business_loan', 'secured_business_loan', 'msme_loan', 'nbfc_business_loan', 'business_term_loan',
            'machinery_loan', 'commercial_vehicle_loan', 'cash_credit', 'overdraft', 'invoice_financing',
            'merchant_financing', 'working_capital', 'other_business',
        ],
        'home_loan_balance_transfer' => [
            'home_loan',
        ],
        'gold_loan' => [
            'personal_loan', 'credit_card_outstanding', 'credit_card_emi', 'consumer_durable_loan', 'bnpl',
            'gold_loan', 'other_personal', 'unsecured_business_loan', 'merchant_financing', 'other_business',
        ],
        'unsecured_business_loan' => [
            'unsecured_business_loan', 'msme_loan', 'nbfc_business_loan', 'business_term_loan', 'merchant_financing',
            'invoice_financing', 'overdraft', 'other_business',
        ],
        'secured_business_loan' => [
            'unsecured_business_loan', 'secured_business_loan', 'msme_loan', 'nbfc_business_loan', 'business_term_loan',
            'machinery_loan', 'commercial_vehicle_loan', 'cash_credit', 'overdraft', 'invoice_financing',
            'merchant_financing', 'working_capital', 'loan_against_property', 'other_business',
        ],
        'vehicle_refinance' => [
            'vehicle_loan', 'commercial_vehicle_loan',
        ],
    ],

    /*
     * Defaults used when Debt Consolidation Settings has no saved value.
     */
    'defaults' => [
        'gst_rate_percent' => 18.0,
        'revolving_minimum_payment_percent' => 5.0,
        'default_tenure_months' => 60,
        'max_projection_months' => 480,
        'rate_mismatch_tolerance_points' => 1.5,
        'non_performing_days_past_due' => 90,
    ],

    /*
     * Retention of data that never became a case (debt-consolidation:enforce-retention,
     * daily). Submitted cases are kept under the business's record-keeping policy.
     */
    'retention' => [
        'drafts_days' => (int) env('DEBT_CONSOLIDATION_DRAFT_RETENTION_DAYS', 180),
        'calculator_snapshots_days' => (int) env('DEBT_CONSOLIDATION_CALCULATOR_RETENTION_DAYS', 90),
    ],

    /*
     * Upload rules for customer and staff documents (encrypted at rest on the
     * private disk).
     */
    'documents' => [
        'max_kilobytes' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
];
