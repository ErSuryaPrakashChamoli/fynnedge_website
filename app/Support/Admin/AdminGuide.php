<?php

namespace App\Support\Admin;

use App\Filament\Clusters\AdminModules;
use App\Filament\Pages;
use App\Filament\Resources;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;

/**
 * Plain-English help for every admin screen: what it is for, when to use it,
 * where its content appears on the website, what must exist first, related
 * screens and the usual next step. Rendered under each screen's title and in
 * an "About this screen" panel at the bottom (see AdminPanelProvider render
 * hooks), and collected on the Admin Guide page.
 *
 * Keyed by resource or page class; a resource's entry covers its list, create
 * and edit screens.
 */
final class AdminGuide
{
    /**
     * @return array<class-string, array{summary: string, purpose: string, when: string, website: ?string, needs?: array<int, string>, related?: array<int, class-string>, next?: array{0: class-string, 1: string}}>
     */
    public static function screens(): array
    {
        return [
            Pages\Dashboard::class => [
                'summary' => 'Your starting point each day: new leads, lead delivery to FynnOn LMS, content status and anything waiting for you.',
                'purpose' => 'This is your starting point for the day. Every card on it links to the screen where you can deal with that item.',
                'when' => 'Open it when you sign in, or whenever you want to check if anything is waiting for you. Examples are leads that failed to reach FynnOn LMS (the loan management system), enquiries nobody has handled yet, and unpublished drafts.',
                'website' => null,
                'related' => [Pages\LeadInbox::class, Pages\HelpCenter::class],
            ],
            Pages\HelpCenter::class => [
                'summary' => 'Explains how the admin is organised and what each module is for, with step-by-step guides for common jobs.',
                'purpose' => 'This is the map of the admin. It lists every module and its screens, and shows the order to do things in, with live progress so you can see which steps are already done.',
                'when' => 'Use it when you are new to the admin, or whenever you are not sure which screen to use for a job.',
                'website' => null,
            ],

            // Leads & Enquiries
            Pages\LeadInbox::class => [
                'summary' => 'All leads from every website form in one list: enquiries, OTP mobile checks, credit score checks and applications.',
                'purpose' => 'This is the one place to see who contacted FynnEdge, from which page and form, whether they verified their mobile number with a one-time password (OTP), and whether the lead reached FynnOn LMS (the loan management system). Click any row to open the original record.',
                'when' => 'Use it every day to follow up leads, and whenever you need to know where a person came from. Click "View journey" to see everything that one mobile number did on the website.',
                'website' => 'This list fills up automatically from the Quick Enquiry box, the enquiry forms on loan pages, the contact form, the credit score pages, mobile OTP checks and the online loan application.',
                'related' => [Resources\ContactEnquiries\ContactEnquiryResource::class, Pages\LeadAnalytics::class, Pages\FynnOnSync::class],
                'next' => [Resources\ContactEnquiries\ContactEnquiryResource::class, 'Update an enquiry\'s follow-up status'],
            ],
            Resources\ContactEnquiries\ContactEnquiryResource::class => [
                'summary' => 'Call-back requests from the Quick Enquiry box, the /quick-enquiry page, the forms on loan pages and the contact page.',
                'purpose' => 'This is your working list of people who asked for a call back. If the same mobile number asks about the same product again, the requests are merged into one record and its repeat count goes up, so you do not get duplicates.',
                'when' => 'Use it to follow up each lead and record where it stands. Move the status from New to Contacted, and then to Converted or Closed.',
                'website' => 'Enquiries come from the Quick Enquiry box on the homepage, the /quick-enquiry page, the enquiry form on every loan page (for example /loans/personal-loan) and the /contact page.',
                'related' => [Pages\LeadInbox::class, Resources\MobileOtpChallenges\MobileOtpChallengeResource::class],
                'next' => [Pages\LeadAnalytics::class, 'See which pages bring the most enquiries'],
            ],
            Resources\MobileOtpChallenges\MobileOtpChallengeResource::class => [
                'summary' => 'A read-only log of every one-time password (OTP) sent and whether the visitor verified it, without ever showing the codes.',
                'purpose' => 'Use it for customer support and to spot misuse. It shows whether the SMS was sent, whether the visitor verified the code, and how many attempts they made.',
                'when' => 'Check it when a visitor says they never received the code, or when you want to spot repeated attempts from the same mobile number.',
                'website' => 'Every form that verifies a mobile number adds an entry here: the Quick Enquiry box, the contact form, the credit score check, the online application and the customer login.',
                'related' => [Pages\LeadInbox::class],
            ],
            Resources\CreditScoreChecks\CreditScoreCheckResource::class => [
                'summary' => 'Free credit score checks that visitors made on the public credit score pages.',
                'purpose' => 'It shows who checked their score, with which credit bureau, and what the result was. Only staff with the right role can see the credit reports and PAN numbers.',
                'when' => 'Use it to follow up visitors who checked their score, and to answer support questions about a particular check.',
                'website' => 'Checks come from the credit score page for each bureau (for example /credit-score/cibil) and from the score check on the homepage.',
                'related' => [Pages\LeadInbox::class, Pages\CreditScorePageSettings::class],
            ],
            Resources\JourneySessions\JourneySessionResource::class => [
                'summary' => 'Online loan applications in progress, one for each visitor who pressed "Apply" on a loan page.',
                'purpose' => 'See how far each visitor got in the online application, what they answered, and which marketing campaign brought them to the website, based on its campaign tags (UTM).',
                'when' => 'Use it to follow up visitors who started an application but did not finish it, or to check which campaign a visitor came from.',
                'website' => 'Applications start on each loan\'s apply page, for example /loans/personal-loan/apply.',
                'needs' => ['journey'],
                'related' => [Resources\Applications\ApplicationResource::class, Pages\AnalyticsDashboard::class],
            ],
            Resources\Applications\ApplicationResource::class => [
                'summary' => 'Loan applications in which the visitor chose a lender, uploaded their documents and pressed Submit.',
                'purpose' => 'Use it to process submitted applications. You can check the documents, see which lender was chosen and update the status.',
                'when' => 'Open it after a visitor submits an application, to review the documents and move the application forward.',
                'website' => 'Records here come from the final steps of the online loan application.',
                'needs' => ['lender_offer', 'document_type'],
                'related' => [Resources\JourneySessions\JourneySessionResource::class, Resources\Customers\CustomerResource::class],
            ],
            Resources\Customers\CustomerResource::class => [
                'summary' => 'People who have a FynnOn customer portal account or a credit bureau record.',
                'purpose' => 'Each customer has one record, which links together their loan applications, credit score checks and credit bureau reports.',
                'when' => 'Use it to look up a customer, or to start a new credit bureau request for them.',
                'website' => 'Customers sign in to the customer portal at /my-profile.',
                'related' => [Pages\LeadInbox::class, Resources\Applications\ApplicationResource::class],
            ],
            Pages\LeadAnalytics::class => [
                'summary' => 'Shows which pages, forms, products and campaigns bring in leads, and how many verify and reach FynnOn LMS.',
                'purpose' => 'It helps you decide where to spend marketing effort. Conversion rates appear only where page views were actually recorded, and nothing is estimated.',
                'when' => 'Use it in your weekly and monthly reviews, and after you launch a new page or campaign.',
                'website' => null,
                'related' => [Pages\LeadInbox::class, Pages\AnalyticsDashboard::class, Pages\SeoAnalytics::class],
            ],
            Pages\AnalyticsDashboard::class => [
                'summary' => 'Tracks the online application step by step: started, completed, eligibility checked, lender chosen and submitted.',
                'purpose' => 'It shows, for each loan product, the step at which visitors give up on the application.',
                'when' => 'Use it to find the step of the application that loses the most applicants.',
                'website' => null,
                'needs' => ['journey'],
                'related' => [Pages\LeadAnalytics::class, Resources\JourneySessions\JourneySessionResource::class],
            ],
            Pages\FynnOnSync::class => [
                'summary' => 'Shows how new website leads are sent to FynnOn LMS: connection status, what was sent, what failed, and a way to retry.',
                'purpose' => 'It proves that each lead reached FynnOn LMS (the loan management system) and lets you resend any that failed. A lead only counts as delivered once the LMS confirms that it received it.',
                'when' => 'Use it when a lead is missing in FynnOn LMS, or when the dashboard shows failed deliveries.',
                'website' => null,
                'needs' => ['fynnon_connection'],
                'related' => [Pages\LeadInbox::class],
            ],

            // Loan Products & Lenders
            Resources\LoanProducts\LoanProductResource::class => [
                'summary' => 'The loan types FynnEdge offers, such as Personal, Home and Business Loan, with their page content, calculator and rate settings.',
                'purpose' => 'This is the main record for each loan product, and it creates the product\'s own page (for example /loans/personal-loan). Landing pages, the online application and lender offers are all linked to a loan product.',
                'when' => 'Start here when you launch a new type of loan. Also use it to change a product\'s calculator or financial settings.',
                'website' => 'Each product has its own page, for example /loans/personal-loan, and appears in the Loans menu.',
                'related' => [Resources\LoanProductContent\LoanProductContentResource::class, Resources\LenderProducts\LenderProductResource::class, Resources\LoanLandingPages\LoanLandingPageResource::class],
                'next' => [Resources\LenderProducts\LenderProductResource::class, 'Add lender offers for this product'],
            ],
            Resources\Lenders\LenderResource::class => [
                'summary' => 'The banks and NBFCs FynnEdge works with, including their name, logo, type and the locations they serve.',
                'purpose' => 'These lender profiles are used by lender offers, rate tables, eligibility checks and the partners page.',
                'when' => 'Use it when you sign up a new lending partner, or to update a lender\'s logo.',
                'website' => 'Lender logos and comparison tables appear on loan pages, on the /partners page and on the homepage.',
                'next' => [Resources\LenderProducts\LenderProductResource::class, 'Add this lender\'s offers'],
            ],
            // Debt Consolidation
            Pages\DebtConsolidationDashboard::class => [
                'summary' => 'The debt consolidation pipeline at a glance: enquiries, assessments, lender submissions, approvals, disbursements and closures, plus follow-ups due today.',
                'purpose' => 'Shows how many cases are at each stage, from the first enquiry to verified closure of the old loans, and lists follow-ups and foreclosure quotations that need attention now. Lender figures are only what lenders have communicated.',
                'when' => 'Start here every day to see what needs doing in debt consolidation.',
                'website' => 'Cases come from the enquiry form and the free assessment on the Debt Consolidation pages (/debt-consolidation).',
                'related' => [Resources\DebtApplications\DebtApplicationResource::class, Pages\DebtConsolidationEnquiries::class],
            ],
            Pages\DebtConsolidationEnquiries::class => [
                'summary' => 'Short debt consolidation enquiries waiting for a call-back: name, verified mobile and the rough figures the customer gave.',
                'purpose' => 'This is the call-back list for people who left their number but have not completed the detailed assessment. Each one is also in Leads & Enquiries (it is the same lead).',
                'when' => 'Use it to call new enquirers, help them add their loans, and move the case status to Contacted.',
                'website' => 'Filled by the "Talk to a debt consolidation advisor" form on /debt-consolidation and the personal and business pages.',
                'related' => [Resources\DebtApplications\DebtApplicationResource::class, Resources\ContactEnquiries\ContactEnquiryResource::class],
            ],
            Resources\DebtApplications\DebtApplicationResource::class => [
                'summary' => 'Every debt consolidation case with its 360° view: liabilities, documents, assessment, suitability, savings, foreclosure, lender matching, lender applications, closure, communication and history.',
                'purpose' => 'This is where advisors work a case from enquiry to completion. Open a case to verify the customer\'s loans, record foreclosure quotations, recalculate, match lenders, record consent, open lender applications, track closures and generate reports. FynnEdge facilitates; lenders decide.',
                'when' => 'Use it for all case work. Change the case status as it moves; "Completed" is set automatically once every closure is verified.',
                'website' => 'Cases are opened by the website\'s enquiry and assessment forms, or by an advisor for a customer who called in.',
                'needs' => [],
                'related' => [Pages\DebtConsolidationDashboard::class, Resources\DebtLenderPolicies\DebtLenderPolicyResource::class],
                'next' => [Resources\DebtLenderPolicies\DebtLenderPolicyResource::class, 'Add lender debt consolidation policies for matching'],
            ],
            Pages\DebtConsolidationCustomers::class => [
                'summary' => 'Customers with at least one debt consolidation case — the same customer records as Leads & Enquiries → Customers.',
                'purpose' => 'A shortcut to the customer records behind debt consolidation cases. There is no separate customer list: each row opens the existing customer record or their latest case.',
                'when' => 'Use it to find a customer\'s cases or their portal account.',
                'website' => null,
                'related' => [Resources\Customers\CustomerResource::class],
            ],
            Resources\DebtLiabilities\DebtLiabilityResource::class => [
                'summary' => 'Every existing loan and card recorded across debt consolidation cases, with its verification status.',
                'purpose' => 'Figures are entered by hand by the customer or an advisor (there is no document reading). Verify each one against the lender\'s statement, correct it if needed (every change is in the audit history), and record the lender\'s foreclosure quotation.',
                'when' => 'Use it for verification work across cases. Day-to-day, work liabilities from the case\'s own tab.',
                'website' => 'Entered by customers in the free assessment (/debt-consolidation/assessment).',
            ],
            Resources\DebtCalculationSnapshots\DebtCalculationSnapshotResource::class => [
                'summary' => 'Every stored calculation (financial assessment): portfolio, affordability, suitability, savings and foreclosure, with formula and scoring versions.',
                'purpose' => 'Calculations are never changed after they are stored; reports are built from them so they can always be reproduced. A new calculation is stored each time a case is recalculated, submitted or matched.',
                'when' => 'Use it to see how a case\'s figures changed over time or which version produced a report.',
                'website' => null,
            ],
            Resources\DebtForeclosureQuotations\DebtForeclosureQuotationResource::class => [
                'summary' => 'Foreclosure quotations from existing lenders across all cases: amounts, validity and verification.',
                'purpose' => 'A verified, current quotation replaces the estimated closure amount in calculations. Quotations expire, so this list highlights those about to lapse.',
                'when' => 'Use it to verify quotations against the lender\'s letter and to chase replacements before they expire.',
                'website' => null,
            ],
            Resources\DebtScenarios\DebtScenarioResource::class => [
                'summary' => 'The consolidation options each case is compared against: rate, tenure, fees and (once known) the sanctioned amount.',
                'purpose' => 'Scenarios are assumptions for comparison, not lender offers. With none, the standard options from Debt Consolidation Settings are used. Recalculate the case after changing them.',
                'when' => 'Use it to model a lender\'s indicative terms or a sanctioned offer.',
                'website' => null,
            ],
            Resources\DebtLenderMatches\DebtLenderMatchResource::class => [
                'summary' => 'How each case compares with each lender\'s debt consolidation policy: reasons it fits, reasons it does not, and what is missing.',
                'purpose' => 'Matching is rule-based and explained line by line. A potential match only means the case fits the lender\'s recorded criteria — never an approval. Advisors record their own review.',
                'when' => 'Use it after "Match lenders" on a case to shortlist lenders with the customer.',
                'website' => null,
                'related' => [Resources\DebtLenderPolicies\DebtLenderPolicyResource::class],
            ],
            Resources\DebtLenderApplications\DebtLenderApplicationResource::class => [
                'summary' => 'Applications facilitated to lenders, each with the lender\'s own status as the lender communicated it.',
                'purpose' => 'A case can have several. Opening one needs the customer\'s consent for that lender. Statuses such as approved or disbursed need the evidence the lender gave; disbursement opens closure tracking but closes nothing by itself.',
                'when' => 'Use it to record every update you receive from a lender.',
                'website' => null,
            ],
            Resources\DebtClosures\DebtClosureResource::class => [
                'summary' => 'Closure of each existing loan after disbursement: quotation, payment, closure request, NOC and verification.',
                'purpose' => 'A loan counts as closed only when its closure is verified: confirmed payment and, for a full closure, a verified NOC or closure letter. Partial closures are verified on confirmed payment.',
                'when' => 'Use it to chase payments and NOCs until every old loan is verified closed.',
                'website' => null,
            ],
            Resources\DebtReports\DebtReportResource::class => [
                'summary' => 'Every generated debt consolidation PDF report, its version and whether the customer can see it.',
                'purpose' => 'Reports are FynnEdge-branded, stored encrypted with a checksum, and built only from stored calculations so they can be reproduced. The internal advisor assessment is never released to customers.',
                'when' => 'Use it to download a report or release one to the customer\'s profile.',
                'website' => 'Released reports appear under My Profile → Debt consolidation.',
            ],
            Resources\DebtScoringConfigs\DebtScoringConfigResource::class => [
                'summary' => 'Versioned weights and thresholds of the Consolidation Suitability Score, separately for individuals and businesses.',
                'purpose' => 'Edit a draft and have a second person activate it. Every calculation records the version that scored it, so changing the scoring never changes earlier results.',
                'when' => 'Use it only when the business agrees to change how suitability is scored.',
                'website' => null,
            ],
            Resources\DebtLenderPolicies\DebtLenderPolicyResource::class => [
                'summary' => 'Each lender offer\'s debt consolidation criteria — debts it takes over, amounts, income, FOIR, collateral — with source and effective dates.',
                'purpose' => 'Extends the existing lender offers (no second lender list). Enter criteria from the lender\'s own document, record the source, and have a second person approve it. Only approved policies within their dates are used for matching.',
                'when' => 'Use it when a lender shares or changes its debt consolidation policy.',
                'website' => null,
                'related' => [Resources\LenderProducts\LenderProductResource::class, Resources\DebtLenderMatches\DebtLenderMatchResource::class],
            ],
            Pages\DebtConsolidationSettings::class => [
                'summary' => 'Whether debt consolidation is live, for whom, which calculators show, calculation assumptions, standard comparison options, notifications, disclaimers and consent wording.',
                'purpose' => 'Website-level configuration of the service. Formulas and scoring are not here. Changing consent wording starts a new consent version automatically.',
                'when' => 'Use it to launch or pause the service and to update disclaimers or consent text after legal review.',
                'website' => 'Controls the /debt-consolidation pages, calculators and forms.',
            ],
            Pages\DebtConsolidationPagesSettings::class => [
                'summary' => 'The shared sections of the Debt Consolidation pages: hero badge, buttons, trust points, steps, who it is for, form headings, and which sections each page shows.',
                'purpose' => 'Each page\'s title, intro, main text, SEO and FAQs are edited on its own row in Website Pages → General Pages; this screen holds the reusable sections around them, in English and Hindi.',
                'when' => 'Use it to reword the debt consolidation pages without a developer.',
                'website' => 'The seven pages under /debt-consolidation, /personal-debt-consolidation and /business-debt-consolidation.',
                'related' => [Resources\Pages\PageResource::class],
            ],
            Resources\LenderProducts\LenderProductResource::class => [
                'summary' => 'What each lender offers for each loan product: loan amounts, tenure, fees and basic eligibility.',
                'purpose' => 'There is one entry for each combination of lender and loan product. Interest rates, eligibility rules and document requirements are all attached to these entries.',
                'when' => 'Use it after you add a new lender or a new loan product.',
                'website' => 'This information appears in the lender comparison tables and in eligibility results.',
                'needs' => ['lender', 'loan_product'],
                'next' => [Resources\LenderProductRates\LenderProductRateResource::class, 'Publish this offer\'s interest rates'],
            ],
            Resources\LenderProductRates\LenderProductRateResource::class => [
                'summary' => 'Interest rates for each lender offer, with supporting evidence and a three-step workflow: draft, approve and publish.',
                'purpose' => 'This is the single source of every interest rate shown on the website. Older versions of each rate are kept.',
                'when' => 'Use it whenever a lender changes its rates. The number badge shows how many rates are due to be checked again.',
                'website' => 'Rates appear in the rate tables, the "starting at" rates and the calculators on loan pages.',
                'needs' => ['lender_offer'],
            ],
            Resources\EligibilityRuleSets\EligibilityRuleSetResource::class => [
                'summary' => 'The eligibility rules for each lender offer, such as age, income and credit score, kept as dated versions.',
                'purpose' => 'These rules decide the eligibility results that visitors see after they answer the application questions. Each change is saved as a new version with the date it takes effect.',
                'when' => 'Use it when a lender changes its criteria. Afterwards, test your changes with the Eligibility Tester.',
                'website' => 'The rules decide the eligibility results shown in the online application.',
                'needs' => ['lender_offer'],
                'next' => [Pages\EligibilityTester::class, 'Test the rules with a sample profile'],
            ],
            Pages\EligibilityTester::class => [
                'summary' => 'Enter a made-up applicant to see which lenders would accept them, and why.',
                'purpose' => 'It checks the eligibility rules without needing a real application. Nothing you enter here is saved.',
                'when' => 'Use it after you change eligibility rules or employer categories.',
                'website' => null,
                'needs' => ['lender_offer', 'eligibility_rules'],
            ],
            Resources\Employers\EmployerResource::class => [
                'summary' => 'A list of employers and the category each lender gives them, used to check eligibility for salaried applicants.',
                'purpose' => 'It lets eligibility rules treat employees of listed companies according to each lender\'s own employer categories.',
                'when' => 'Use it when a lender sends an updated employer list. You can import the list from a file instead of typing it in.',
                'website' => null,
                'needs' => ['lender'],
            ],
            Resources\JourneyDefinitions\JourneyDefinitionResource::class => [
                'summary' => 'The questions in the online loan application, set up separately for each loan product.',
                'purpose' => 'It defines the steps and fields of the application. Each change is saved as a new version, and only the published version is shown to visitors.',
                'when' => 'Use it when you launch the online application for a loan product, or when you need to change its questions.',
                'website' => 'The questions appear on each loan\'s apply page, for example /loans/personal-loan/apply.',
                'needs' => ['loan_product'],
            ],
            Resources\DocumentTypes\DocumentTypeResource::class => [
                'summary' => 'The kinds of documents applicants can upload, such as PAN card, payslips and bank statements.',
                'purpose' => 'This is a shared list that document requirements choose from.',
                'when' => 'Set these up before you create document requirements.',
                'website' => 'They appear in the document upload step of the online application.',
                'next' => [Resources\DocumentRequirements\DocumentRequirementResource::class, 'Choose which documents each offer needs'],
            ],
            Resources\DocumentRequirements\DocumentRequirementResource::class => [
                'summary' => 'Which documents each lender offer needs, and whether each one is mandatory.',
                'purpose' => 'It builds the upload checklist that an applicant sees after choosing a lender.',
                'when' => 'Use it after you add a new lender offer.',
                'website' => 'The checklist appears in the document upload step of the online application.',
                'needs' => ['lender_offer', 'document_type'],
            ],

            // Credit Bureau
            Pages\CibilDashboard::class => ['summary' => 'An overview of CIBIL activity: paid requests, money saved by reusing reports, failures and items that need review.', 'purpose' => 'Use it to keep an eye on how much CIBIL is used and what it costs.', 'when' => 'Check it every day, or whenever CIBIL requests are failing.', 'website' => null, 'related' => [Resources\CibilCreditRequests\CibilCreditRequestResource::class, Pages\CibilSettings::class]],
            Resources\CibilCreditRequests\CibilCreditRequestResource::class => ['summary' => 'Every paid CIBIL request (a score, a full report or a PDF), with the report available to authorised staff.', 'purpose' => 'View the results, download PDFs, and check and resolve failed requests. New requests are started from a customer or an application, not from this screen.', 'when' => 'Use it to find or review a CIBIL report.', 'website' => null, 'related' => [Pages\CibilDashboard::class]],
            Pages\CibilSettings::class => ['summary' => 'The business rules for CIBIL requests, and the switches for the public Get CIBIL Score page.', 'purpose' => 'Turn CIBIL requests on or off, and choose how the public score page works. The API access tokens are kept on the server, not on this screen.', 'when' => 'Use it when you switch CIBIL on, or to change the rules for reusing recent reports and preventing duplicate requests.', 'website' => 'These settings control the public Get CIBIL Score page.'],
            Pages\CibilAuditHistory::class => ['summary' => 'Every recorded CIBIL event in a single feed.', 'purpose' => 'It keeps staff accountable by showing who requested, viewed or downloaded each item, and when.', 'when' => 'Use it for audits and investigations.', 'website' => null],
            Pages\ExperianDashboard::class => ['summary' => 'An overview of Experian activity: paid requests, money saved by reusing reports, failures and items that need review.', 'purpose' => 'Use it to keep an eye on how much Experian is used and what it costs.', 'when' => 'Check it every day, or whenever Experian requests are failing.', 'website' => null, 'related' => [Resources\ExperianCreditRequests\ExperianCreditRequestResource::class, Pages\ExperianSettings::class]],
            Resources\ExperianCreditRequests\ExperianCreditRequestResource::class => ['summary' => 'Every paid Experian request, with the report available to authorised staff.', 'purpose' => 'View the results, download PDFs, and check and resolve failed requests.', 'when' => 'Use it to find or review an Experian report.', 'website' => null],
            Pages\ExperianSettings::class => ['summary' => 'The business rules for Experian requests.', 'purpose' => 'Turn Experian requests on or off, and set the rules for reusing recent reports. The API access tokens are kept on the server, not on this screen.', 'when' => 'Use it when you switch Experian on, or to change its rules.', 'website' => null],
            Pages\ExperianAuditHistory::class => ['summary' => 'Every recorded Experian event in a single feed.', 'purpose' => 'It keeps staff accountable by recording who accessed Experian data.', 'when' => 'Use it for audits and investigations.', 'website' => null],
            Pages\EquifaxDashboard::class => ['summary' => 'An overview of Equifax activity: paid requests, money saved by reusing reports, failures and items that need review.', 'purpose' => 'Use it to keep an eye on how much Equifax is used and what it costs.', 'when' => 'Check it every day, or whenever Equifax requests are failing.', 'website' => null, 'related' => [Resources\EquifaxCreditRequests\EquifaxCreditRequestResource::class, Pages\EquifaxSettings::class]],
            Resources\EquifaxCreditRequests\EquifaxCreditRequestResource::class => ['summary' => 'Every paid Equifax request, with the report available to authorised staff.', 'purpose' => 'View the results, and check and resolve failed requests.', 'when' => 'Use it to find or review an Equifax report.', 'website' => null],
            Pages\EquifaxSettings::class => ['summary' => 'The business rules for Equifax requests.', 'purpose' => 'Turn Equifax requests on or off, and set the rules for reusing recent reports. The API access tokens are kept on the server, not on this screen.', 'when' => 'Use it when you switch Equifax on, or to change its rules.', 'website' => null],
            Pages\EquifaxAuditHistory::class => ['summary' => 'Every recorded Equifax event in a single feed.', 'purpose' => 'It keeps staff accountable by recording who accessed Equifax data.', 'when' => 'Use it for audits and investigations.', 'website' => null],
            Pages\CrifDashboard::class => ['summary' => 'An overview of CRIF activity: paid requests, money saved by reusing reports, failures and items that need review.', 'purpose' => 'Use it to keep an eye on how much CRIF is used and what it costs.', 'when' => 'Check it every day, or whenever CRIF requests are failing.', 'website' => null, 'related' => [Resources\CrifReports\CrifReportResource::class, Pages\CrifSettings::class]],
            Resources\CrifReports\CrifReportResource::class => ['summary' => 'Every paid attempt to get a CRIF report, with the report available to authorised staff.', 'purpose' => 'View the results, download PDFs, and check and resolve failed requests.', 'when' => 'Use it to find or review a CRIF report.', 'website' => null],
            Pages\CrifSettings::class => ['summary' => 'The business rules for CRIF reports.', 'purpose' => 'Turn CRIF requests on or off, and set the rules for reusing recent reports. The API access token is kept on the server, not on this screen.', 'when' => 'Use it when you switch CRIF on, or to change its rules.', 'website' => null],

            // Website Pages
            Pages\CreateWebsitePage::class => [
                'summary' => 'Pick what you want to publish and this guide opens the right editor, so you never have to guess which kind of page to make.',
                'purpose' => 'It explains each type of page (general page, loan landing page, campaign page and blog article), where each one appears, what each one needs first, and the steps to publish it.',
                'when' => 'Use it every time you want to add a new page to the website.',
                'website' => null,
            ],
            Resources\Pages\PageResource::class => [
                'summary' => 'General information pages such as About, Careers, FAQs and legal pages, plus any new page with its own address on fynnedge.com.',
                'purpose' => 'These are simple pages with a title, body text, FAQs and SEO settings. They are not meant for loan products, which belong in Loan Landing Pages.',
                'when' => 'Use it for policies, company information or any standalone page that is not a loan offer.',
                'website' => 'Each page has its own address on fynnedge.com, for example fynnedge.com/disclaimer. Legal pages also appear in the footer.',
                'related' => [Pages\CreateWebsitePage::class, Resources\PageSeos\PageSeoResource::class, Resources\NavigationLinks\NavigationLinkResource::class],
                'next' => [Resources\NavigationLinks\NavigationLinkResource::class, 'Add this page to the footer Quick Links'],
            ],
            Resources\LoanLandingPages\LoanLandingPageResource::class => [
                'summary' => 'Lead-generating pages for one loan product, aimed at an amount, type, need or city, for example "Personal Loan in Pune".',
                'purpose' => 'Each page carries its product\'s enquiry form, rates and calculator, so it captures leads. It appears in the Loans menu under the group you choose for it.',
                'when' => 'Use it for SEO pages that target a particular search, such as "₹5 lakh personal loan", or for pages used in ad campaigns.',
                'website' => 'Each page sits under its loan product\'s address, for example /loans/personal-loan/personal-loan-in-pune, and is listed in the Loans mega menu.',
                'needs' => ['loan_product'],
                'related' => [Resources\LoanLandingPageContent\LoanLandingPageContentResource::class, Resources\LoanLandingPageSeo\LoanLandingPageSeoResource::class],
                'next' => [Resources\LoanLandingPageSeo\LoanLandingPageSeoResource::class, 'Set its search title and description'],
            ],
            Resources\LoanLandingPageContent\LoanLandingPageContentResource::class => [
                'summary' => 'Edit only the marketing text of an existing landing page, without changing its address, product or amounts.',
                'purpose' => 'This is a safe editor for content writers. It changes the words on the page, but never what the page is connected to.',
                'when' => 'Use it to update the wording on landing pages.',
                'website' => 'The text appears on the landing page itself, for example /loans/personal-loan/personal-loan-in-pune.',
                'needs' => ['landing_page'],
            ],
            Resources\LoanProductContent\LoanProductContentResource::class => [
                'summary' => 'Edit only the marketing text of a loan product page: headline, features, benefits, documents and steps.',
                'purpose' => 'This is a safe editor for content writers. The calculator and financial settings stay in Loan Products.',
                'when' => 'Use it to update the wording on loan product pages such as /loans/personal-loan.',
                'website' => 'The text appears on the loan product\'s page, for example /loans/personal-loan.',
                'needs' => ['loan_product'],
            ],
            Resources\CalculatorPages\CalculatorPageResource::class => [
                'summary' => 'The "About this calculator" text and the SEO settings for each calculator page.',
                'purpose' => 'There is one fixed page for each calculator. You can edit these pages, but you cannot add new calculators here.',
                'when' => 'Use it to improve a calculator page\'s explanation or how it appears in search results.',
                'website' => 'The text appears on each calculator page, for example /calculators/emi/home-loan.',
                'related' => [Pages\CalculatorPagesSettings::class],
            ],
            Resources\CreditScorePages\CreditScorePageResource::class => [
                'summary' => 'The long-form content shown further down each credit bureau\'s free credit score page.',
                'purpose' => 'These are fixed pages, one for each bureau: CIBIL, Experian, Equifax and CRIF.',
                'when' => 'Use it to improve the explanation on a credit score page.',
                'website' => 'The content appears on each bureau\'s score page, for example /credit-score/cibil.',
                'related' => [Pages\CreditScorePageSettings::class],
            ],
            Resources\Banners\BannerResource::class => [
                'summary' => 'The slides in the rotating banner (carousel) at the top of the homepage.',
                'purpose' => 'Set the image, heading, button and colours for each slide. You can also schedule when a slide is shown.',
                'when' => 'Use it to promote a new offer or a seasonal campaign on the homepage.',
                'website' => 'The slides appear at the top of the homepage.',
            ],
            Resources\MarketingSections\MarketingSectionResource::class => [
                'summary' => 'The wording of fixed sections, such as the homepage calls to action and the enquiry form headings.',
                'purpose' => 'An entry here replaces a section\'s default wording. Delete the entry to go back to the default text.',
                'when' => 'Use it to change the text of a homepage section or a form section.',
                'website' => 'Each entry changes the section chosen in its Placement field.',
            ],
            Resources\PromoBars\PromoBarResource::class => [
                'summary' => 'Offer bars that slide up from the bottom of the pages you choose and stay in view while visitors scroll.',
                'purpose' => 'Use them for time-limited promotions. A bar can show a countdown, appear after scrolling, after a delay or when the visitor is about to leave, and be shown only on certain devices.',
                'when' => 'Use it for a limited-time offer or to push a campaign.',
                'website' => 'The bar appears at the bottom of the pages you pick.',
            ],
            Resources\NavigationLinks\NavigationLinkResource::class => [
                'summary' => 'Extra links in the "Quick Links" column of the website footer.',
                'purpose' => 'Use it to add new pages to the footer. The header menus and the footer\'s Company and Legal columns are fixed and cannot be changed here.',
                'when' => 'Use it after you publish a page that visitors should be able to reach from every page.',
                'website' => 'The links appear in the Quick Links column of the footer.',
            ],

            // Blog & Content
            Resources\Articles\ArticleResource::class => [
                'summary' => 'Blog articles and guides, each with a category, image, author, SEO settings and an optional publishing schedule.',
                'purpose' => 'Articles bring in visitors from search engines and build trust in FynnEdge. Choose an author to show their name and photo as a byline.',
                'when' => 'Use it for guides, news and explainers.',
                'website' => 'Each article has its own page (for example /resources/what-is-foir), is listed on the Resources page, and can also appear on the homepage.',
                'needs' => ['author'],
                'related' => [Resources\Authors\AuthorResource::class, Resources\NewsletterCampaigns\NewsletterCampaignResource::class],
                'next' => [Resources\NewsletterCampaigns\NewsletterCampaignResource::class, 'Send this article to subscribers'],
            ],
            Resources\Authors\AuthorResource::class => [
                'summary' => 'The people who write FynnEdge articles, with their name, photo, job title and short bio.',
                'purpose' => 'Authors appear as a byline on their articles and have their own author page. Deactivating an author hides them without removing them from their articles.',
                'when' => 'Use it before you publish an article by someone new, or to update a writer\'s profile.',
                'website' => 'Authors appear in article bylines, in the "About the author" box, and on their own author page, whose address starts with /resources/authors/.',
                'next' => [Resources\Articles\ArticleResource::class, 'Assign authors to articles'],
            ],
            Resources\Faqs\FaqResource::class => [
                'summary' => 'General questions and answers shown on the /faqs page.',
                'purpose' => 'These are company-wide questions. Questions about a single product or page are added on that product or page instead.',
                'when' => 'Use it to add a common question or reword an existing one.',
                'website' => 'The questions appear on the /faqs page.',
                'related' => [Resources\PageFaqs\PageFaqResource::class],
            ],
            Resources\PageFaqs\PageFaqResource::class => [
                'summary' => 'Questions and answers attached to particular pages, such as calculators, credit score pages and the contact page.',
                'purpose' => 'It adds an expandable question-and-answer section to the pages you choose, along with FAQ structured data that search engines can read.',
                'when' => 'Use it to answer questions on a page that has no FAQ editor of its own.',
                'website' => 'Each FAQ appears on the pages chosen for it.',
            ],
            Pages\MediaGovernance::class => [
                'summary' => 'Every uploaded image and video, showing where each one is used and which files are unused or missing.',
                'purpose' => 'Use it to find unused uploads and remove them safely.',
                'when' => 'Use it for regular clean-ups, or when an image is missing on the website.',
                'website' => null,
            ],
            Resources\Testimonials\TestimonialResource::class => ['summary' => 'Written testimonials from customers.', 'purpose' => 'Each testimonial is a quote with the customer\'s name, rating and photo, shown in the testimonial carousel.', 'when' => 'Add one when a customer agrees to share their feedback.', 'website' => 'Testimonials appear in the testimonial carousel on the homepage.'],
            Resources\VideoTestimonials\VideoTestimonialResource::class => ['summary' => 'Video testimonials from customers, shown on the pages you choose.', 'purpose' => 'Each video is shown as a video card, and you can also show it in a floating video bubble.', 'when' => 'Use it when you have a new customer video.', 'website' => 'Each video appears on the pages chosen for it.'],
            Resources\SocialVideos\SocialVideoResource::class => ['summary' => 'Videos from FynnEdge\'s Instagram, Facebook, LinkedIn and YouTube posts.', 'purpose' => 'It shows a row of social media videos on the pages you choose.', 'when' => 'Use it to feature a social media post on the website.', 'website' => 'Each video appears on the pages chosen for it.'],
            Resources\Achievements\AchievementResource::class => ['summary' => 'The headline numbers shown in the stats strip on the homepage.', 'purpose' => 'These are figures such as "50+ partner lenders". Please keep them accurate.', 'when' => 'Update them whenever a milestone changes.', 'website' => 'The numbers appear in the stats strip on the homepage.'],
            Resources\CompanyPhotos\CompanyPhotoResource::class => ['summary' => 'Photos for the "Life at FynnEdge" gallery.', 'purpose' => 'Add photos of the team and the office here.', 'when' => 'Use it after a company event, or when the office changes.', 'website' => 'The photos appear on the /careers and /about pages.'],

            // SEO & Performance
            Pages\SeoAnalytics::class => ['summary' => 'Site-wide SEO, search engine indexing and verification, tracking codes (such as GA4, GTM and Meta) and cookie consent.', 'purpose' => 'This is the one place for every tracking code and search engine setting.', 'when' => 'Use it when you add a tracking tag, or to change whether search engines may index the website.', 'website' => 'These settings are added to the hidden code at the top of every page (the <head> section), which visitors do not see.'],
            Resources\PageSeos\PageSeoResource::class => ['summary' => 'The search title, description and social media preview for any address on the website.', 'purpose' => 'It sets the SEO for pages that have no SEO section of their own, such as the homepage, the main /calculators page and the contact page.', 'when' => 'Use it to improve how a page appears in Google or when it is shared on social media.', 'website' => 'It changes the search result snippet for the address you choose.'],
            Resources\LoanProductSeo\LoanProductSeoResource::class => ['summary' => 'The search title, description and social media preview for each loan product page.', 'purpose' => 'This editor changes SEO only. The page content and financial settings are edited on other screens.', 'when' => 'Use it for SEO work on loan product pages such as /loans/personal-loan.', 'website' => 'It changes how each loan product page, for example /loans/personal-loan, appears in search results.', 'needs' => ['loan_product']],
            Resources\LoanLandingPageSeo\LoanLandingPageSeoResource::class => ['summary' => 'The search title, description and indexing settings for each loan landing page.', 'purpose' => 'This editor changes SEO only. Set a landing page to "noindex" here if it is only meant for ad campaigns.', 'when' => 'Use it for SEO work on landing pages.', 'website' => 'It changes how each landing page, for example /loans/personal-loan/personal-loan-in-pune, appears in search results.', 'needs' => ['landing_page']],
            Pages\StructuredData::class => ['summary' => 'How the site-wide structured data (JSON-LD) for search engines is set up, including the Organization, WebSite and WebPage types.', 'purpose' => 'These are advanced settings that help search engines show rich results for FynnEdge.', 'when' => 'Change these only when you have agreed a structured data change with the SEO team.', 'website' => 'They affect the structured data on every page, which search engines read but visitors do not see.'],
            Resources\SchemaTemplates\SchemaTemplateResource::class => ['summary' => 'Reusable blocks of structured data (JSON-LD) that a page\'s SEO section can apply.', 'purpose' => 'Write the structured data once and reuse it on many pages.', 'when' => 'Use it when several pages need the same structured data.', 'website' => 'A template affects the structured data of every page that uses it.'],
            Resources\Redirects\RedirectResource::class => ['summary' => 'Send an old or wrong address to the right page (a 301 redirect), or mark it as permanently gone (410).', 'purpose' => 'Redirects keep links and search rankings working when a page moves.', 'when' => 'Use it after you rename or delete a page, or to fix an address that shows a "page not found" (404) error.', 'website' => 'Redirects are applied before any page loads.', 'related' => [Resources\NotFoundUrls\NotFoundUrlResource::class]],
            Resources\NotFoundUrls\NotFoundUrlResource::class => ['summary' => 'Addresses that visitors and search engines asked for but that do not exist (404 errors), with how often each was hit.', 'purpose' => 'Use it to find broken links, then fix each one with a redirect.', 'when' => 'Check it as part of your weekly SEO review.', 'website' => null, 'next' => [Resources\Redirects\RedirectResource::class, 'Add a redirect for a broken link']],

            // Website Settings
            Pages\Settings::class => ['summary' => 'Site-wide settings such as the business profile, contact details, homepage hero, appearance and more.', 'purpose' => 'The values set here are used across the whole website.', 'when' => 'Use it when company details, or wording used across the whole site, need to change.', 'website' => 'These settings appear in the header, in the footer, on the homepage and wherever contact details are shown.'],
            Pages\AboutPageSettings::class => ['summary' => 'The wording of each section of the About page.', 'purpose' => 'It lets you edit the /about page without any changes to code.', 'when' => 'Use it when the company story or values change.', 'website' => 'The text appears on the /about page.'],
            Pages\QuickEnquiryPageSettings::class => ['summary' => 'The wording of the /quick-enquiry page, which is used for ad campaigns.', 'purpose' => 'This is a focused page for collecting leads from campaigns.', 'when' => 'Update it before you point an ad campaign at /quick-enquiry.', 'website' => 'The text appears on the /quick-enquiry page.'],
            Pages\CalculatorPagesSettings::class => ['summary' => 'The headings on the main /calculators page and on each calculator page.', 'purpose' => 'It sets the page-level wording for all the calculators.', 'when' => 'Use it when the wording on the calculator pages needs updating.', 'website' => 'The text appears on the /calculators page and on each calculator page.'],
            Pages\CreditScorePageSettings::class => ['summary' => 'The wording of each credit bureau\'s free credit score page.', 'purpose' => 'It sets the text around the credit score check form.', 'when' => 'Use it when the wording on a credit score page needs updating.', 'website' => 'The text appears on each bureau\'s score page, for example /credit-score/cibil.'],
            Resources\HowItWorksSteps\HowItWorksStepResource::class => ['summary' => 'The steps in the "How it works" section.', 'purpose' => 'They explain FynnEdge\'s process in a few simple steps.', 'when' => 'Update them when the process changes.', 'website' => 'The steps appear in the "How it works" section on the homepage.'],
            Resources\GrievanceLevels\GrievanceLevelResource::class => ['summary' => 'The grievance redressal matrix, showing each complaint level with its contacts and turnaround times.', 'purpose' => 'These are the contact details for complaints that regulations require FynnEdge to publish.', 'when' => 'Update it when a grievance officer changes.', 'website' => 'The matrix appears on the /grievance page.'],
            Resources\JobOpenings\JobOpeningResource::class => ['summary' => 'Open job positions listed on the careers page.', 'purpose' => 'Use it to advertise jobs and recruit new staff.', 'when' => 'Use it when hiring for a role opens or closes.', 'website' => 'The jobs appear on the /careers page.'],

            // Email Marketing
            Pages\NewsletterDashboard::class => ['summary' => 'Newsletter health: subscribers, growth, where people signed up and which articles bring in the most subscribers.', 'purpose' => 'It gives you an overview of the mailing list.', 'when' => 'Use it in your monthly marketing review.', 'website' => null],
            Resources\NewsletterSubscribers\NewsletterSubscriberResource::class => ['summary' => 'People who joined the mailing list, which they can only do by signing up themselves.', 'purpose' => 'This list is the record of each person\'s consent, which is why you cannot add email addresses by hand.', 'when' => 'Use it to check or export the list, or to deal with a question about unsubscribing.', 'website' => 'People join through the newsletter sign-up boxes on the website.'],
            Resources\NewsletterCampaigns\NewsletterCampaignResource::class => ['summary' => 'Emails sent to subscribers, which can optionally be based on an article.', 'purpose' => 'Write, schedule and send campaigns, then see how many were delivered and opened.', 'when' => 'Use it for every newsletter you send.', 'website' => null, 'needs' => ['newsletter_template']],
            Resources\NewsletterTemplates\NewsletterTemplateResource::class => ['summary' => 'The email layouts that campaigns use.', 'purpose' => 'They are reusable layouts with FynnEdge branding.', 'when' => 'Set one up before your first campaign, or when the emails are redesigned.', 'website' => null, 'next' => [Resources\NewsletterCampaigns\NewsletterCampaignResource::class, 'Create a campaign']],
            Resources\NewsletterSegments\NewsletterSegmentResource::class => ['summary' => 'Groups of subscribers, based on their interests or where they signed up, that a campaign can be sent to.', 'purpose' => 'Use segments to send relevant emails to part of the list instead of everyone.', 'when' => 'Set one up before you send a targeted campaign.', 'website' => null],
            Pages\NewsletterSettings::class => ['summary' => 'The sender name and email address, and whether new subscribers must confirm by email (double opt-in).', 'purpose' => 'These settings apply to every newsletter email and help it reach people\'s inboxes.', 'when' => 'Set them up once. You will rarely need to change them.', 'website' => null],

            // Administration
            Resources\Users\UserResource::class => ['summary' => 'Admin accounts and the role each one has.', 'purpose' => 'It controls who can sign in to this admin, and with which role.', 'when' => 'Use it when someone joins or leaves the team.', 'website' => null, 'related' => [RoleResource::class]],
            RoleResource::class => ['summary' => 'Roles and their permissions, which decide what each role can see and do.', 'purpose' => 'It gives detailed control over which screens and actions each role can use.', 'when' => 'Use it when a team needs more or less access.', 'website' => null],
            Resources\ApiClients\ApiClientResource::class => ['summary' => 'Other systems, such as FynnOn LMS, that are allowed to connect to the FynnEdge API with their own key.', 'purpose' => 'Each client acts as a service account with the role you choose. Its key is shown only once, so copy it straight away.', 'when' => 'Use it when you connect a new system to the FynnEdge API.', 'website' => null],
            Pages\AdminActivity::class => ['summary' => 'A record of who changed what in the admin, and when.', 'purpose' => 'It is a feed of every record that was created, updated or deleted.', 'when' => 'Use it for audits, or to find out who changed a page.', 'website' => null],
        ];
    }

    /**
     * Short description of each main module, for the guide and the dashboard.
     *
     * @return array<class-string, array{summary: string, when: string}>
     */
    public static function modules(): array
    {
        $summaries = [
            'Leads' => ['summary' => 'Every enquiry, OTP mobile check, credit score check and application that comes in from the website, plus reports on where leads come from and their delivery to FynnOn LMS (the loan management system).', 'when' => 'Following up leads every day, and reviewing marketing results.'],
            'DebtConsolidation' => ['summary' => 'Debt consolidation cases from the first enquiry to verified closure of the old loans: assessments, lender matching, lender applications, closures and reports.', 'when' => 'Working debt consolidation cases every day.'],
            'LoanCatalog' => ['summary' => 'The loan business itself: loan products, lenders, lender offers, interest rates, eligibility rules and the setup of the online application.', 'when' => 'Launching or changing a loan product or a lender.'],
            'CreditBureau' => ['summary' => 'The four credit bureaus (CIBIL, Experian, Equifax and CRIF), with their dashboards, reports, requests, failures, settings and audit history.', 'when' => 'Running credit report operations and setting up each bureau.'],
            'WebsitePages' => ['summary' => 'Every kind of public page, including general pages, loan landing pages, calculator pages and credit score pages, plus the homepage sections. To make a new page, start from "Create a Page".', 'when' => 'Creating new pages on the website, or editing existing ones.'],
            'Content' => ['summary' => 'Blog articles and their authors, FAQs, testimonials, social media videos and the media library.', 'when' => 'Publishing articles and managing the content that supports them.'],
            'Seo' => ['summary' => 'Search titles and descriptions, structured data, redirects, monitoring of missing pages (404 errors) and tracking codes.', 'when' => 'SEO work and setting up analytics.'],
            'SiteSettings' => ['summary' => 'Site-wide settings, the wording of fixed pages (About, Quick Enquiry, Calculators and Credit Score) and company information.', 'when' => 'Changing company details or the wording of fixed pages.'],
            'Marketing' => ['summary' => 'The email newsletter: subscribers, campaigns, templates, segments and sender settings.', 'when' => 'Planning and sending email campaigns.'],
            'Administration' => ['summary' => 'Admin users, roles and permissions, API clients and the activity log.', 'when' => 'Managing who has access to the admin, and carrying out audits.'],
        ];

        $modules = [];

        foreach (AdminModules::clusters() as $cluster) {
            $key = (string) str(class_basename($cluster))->beforeLast('Cluster');
            $modules[$cluster] = $summaries[$key] ?? ['summary' => '', 'when' => ''];
        }

        return $modules;
    }

    /**
     * The entry for the screen being rendered, from a render hook's scopes
     * ([page class, resource class] for resource pages).
     *
     * @param  array<int, string>  $scopes
     * @return array{screen: class-string, summary: string, purpose: string, when: string, website: ?string, needs: array<int, string>, related: array<int, class-string>, next: ?array{0: class-string, 1: string}}|null
     */
    public static function forScopes(array $scopes): ?array
    {
        $screens = self::screens();

        foreach (array_reverse($scopes) as $scope) {
            if (isset($screens[$scope])) {
                return [
                    'screen' => $scope,
                    'needs' => [],
                    'related' => [],
                    'next' => null,
                    ...$screens[$scope],
                ];
            }
        }

        return null;
    }
}
