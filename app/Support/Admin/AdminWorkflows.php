<?php

namespace App\Support\Admin;

use App\Filament\Pages;
use App\Filament\Resources;

/**
 * Step-by-step guides for the jobs that span several screens, in the order
 * they must be done. A step tied to a requirement shows live progress
 * (done / to do); the first unfinished one is highlighted as the next action.
 */
final class AdminWorkflows
{
    /**
     * @return array<string, array{title: string, description: string, steps: array<int, array{label: string, detail: string, screen: ?class-string, requirement?: string, optional?: bool}>}>
     */
    public static function all(): array
    {
        return [
            'loan-product' => [
                'title' => 'Launch a new loan product',
                'description' => 'From a new lender to a live loan page that captures leads and takes applications.',
                'steps' => [
                    ['label' => 'Add the lenders', 'detail' => 'Banks/NBFCs with logo and locations.', 'screen' => Resources\Lenders\LenderResource::class, 'requirement' => 'lender'],
                    ['label' => 'Create the loan product', 'detail' => 'Name, address (slug), calculator and amount/tenure limits. Keep it as a draft for now.', 'screen' => Resources\LoanProducts\LoanProductResource::class, 'requirement' => 'loan_product'],
                    ['label' => 'Write the page content', 'detail' => 'Headline, features, benefits, eligibility, documents and steps.', 'screen' => Resources\LoanProductContent\LoanProductContentResource::class],
                    ['label' => 'Add lender offers', 'detail' => 'One per lender for this product: amounts, tenure, fees.', 'screen' => Resources\LenderProducts\LenderProductResource::class, 'requirement' => 'lender_offer'],
                    ['label' => 'Publish interest rates', 'detail' => 'Draft → approve → publish, with evidence.', 'screen' => Resources\LenderProductRates\LenderProductRateResource::class, 'requirement' => 'interest_rate'],
                    ['label' => 'Add eligibility rules', 'detail' => 'Then try a sample profile in the Eligibility Tester.', 'screen' => Resources\EligibilityRuleSets\EligibilityRuleSetResource::class, 'requirement' => 'eligibility_rules'],
                    ['label' => 'Set document requirements', 'detail' => 'What applicants upload for each lender offer.', 'screen' => Resources\DocumentRequirements\DocumentRequirementResource::class, 'requirement' => 'document_requirement'],
                    ['label' => 'Set up the application journey', 'detail' => 'The questions behind the "Apply" button.', 'screen' => Resources\JourneyDefinitions\JourneyDefinitionResource::class, 'requirement' => 'journey'],
                    ['label' => 'Write the search title and description', 'detail' => 'How the page looks in Google.', 'screen' => Resources\LoanProductSeo\LoanProductSeoResource::class],
                    ['label' => 'Preview, then publish the product', 'detail' => 'Use Preview on the product, then set it to Published.', 'screen' => Resources\LoanProducts\LoanProductResource::class, 'requirement' => 'published_loan_product'],
                    ['label' => 'Add landing pages (optional)', 'detail' => 'By amount, city, type or need — each one captures leads.', 'screen' => Resources\LoanLandingPages\LoanLandingPageResource::class, 'requirement' => 'landing_page', 'optional' => true],
                ],
            ],
            'website-page' => [
                'title' => 'Create a website page',
                'description' => 'Choose the right page type first — the Create a Page guide explains each one.',
                'steps' => [
                    ['label' => 'Choose the page type', 'detail' => 'General page, loan landing page, campaign page or blog article.', 'screen' => Pages\CreateWebsitePage::class],
                    ['label' => 'Write the page and save it as a draft', 'detail' => 'Drafts are never visible to visitors.', 'screen' => null],
                    ['label' => 'Fill in the SEO section', 'detail' => 'Search title, description and indexing (noindex for ad-only pages).', 'screen' => null],
                    ['label' => 'Preview', 'detail' => 'The Preview button opens a private link showing the draft exactly as it will look.', 'screen' => null],
                    ['label' => 'Publish', 'detail' => 'Set the status to Published (optionally with a start and end date).', 'screen' => null],
                    ['label' => 'Link to it (optional)', 'detail' => 'Add it to the footer Quick Links so visitors can find it.', 'screen' => Resources\NavigationLinks\NavigationLinkResource::class, 'optional' => true],
                ],
            ],
            'article' => [
                'title' => 'Publish a blog article',
                'description' => 'Articles build search traffic; an author byline adds trust.',
                'steps' => [
                    ['label' => 'Add the author', 'detail' => 'Name, photo, designation and a short bio.', 'screen' => Resources\Authors\AuthorResource::class, 'requirement' => 'author'],
                    ['label' => 'Write the article', 'detail' => 'Title, category, excerpt, cover image and body; choose the author.', 'screen' => Resources\Articles\ArticleResource::class],
                    ['label' => 'Fill in the SEO section', 'detail' => 'Search title, description and social image.', 'screen' => Resources\Articles\ArticleResource::class],
                    ['label' => 'Preview and publish', 'detail' => 'Publish now or schedule a date.', 'screen' => Resources\Articles\ArticleResource::class],
                    ['label' => 'Email it to subscribers (optional)', 'detail' => 'Create a newsletter campaign from the article.', 'screen' => Resources\NewsletterCampaigns\NewsletterCampaignResource::class, 'optional' => true],
                ],
            ],
            'lead-flow' => [
                'title' => 'How a lead travels',
                'description' => 'What happens, automatically, from a visitor\'s first page to FynnOn LMS.',
                'steps' => [
                    ['label' => 'Visitor lands on a page', 'detail' => 'The page (and campaign tags, where present) is recorded.', 'screen' => Pages\LeadAnalytics::class],
                    ['label' => 'Submits a form', 'detail' => 'Quick Enquiry, loan-page enquiry, contact form, credit score check or application.', 'screen' => Pages\LeadInbox::class],
                    ['label' => 'Verifies the mobile number by OTP', 'detail' => 'Where the form requires it. Codes are never visible to staff.', 'screen' => Resources\MobileOtpChallenges\MobileOtpChallengeResource::class],
                    ['label' => 'Saved and de-duplicated', 'detail' => 'Repeat enquiries from the same number for the same product are merged, not duplicated.', 'screen' => Resources\ContactEnquiries\ContactEnquiryResource::class],
                    ['label' => 'Sent to FynnOn LMS', 'detail' => 'Within seconds, with automatic retries. Counted as delivered only when the LMS confirms.', 'screen' => Pages\FynnOnSync::class, 'requirement' => 'fynnon_connection'],
                    ['label' => 'Follow up and record the outcome', 'detail' => 'Update the enquiry status; review results in Lead Analytics.', 'screen' => Pages\LeadAnalytics::class],
                ],
            ],
            'newsletter' => [
                'title' => 'Send a newsletter',
                'description' => 'Subscribers join only through their own signup.',
                'steps' => [
                    ['label' => 'Check sender settings', 'detail' => 'Sender name/address and double opt-in.', 'screen' => Pages\NewsletterSettings::class],
                    ['label' => 'Create a template', 'detail' => 'The email layout.', 'screen' => Resources\NewsletterTemplates\NewsletterTemplateResource::class, 'requirement' => 'newsletter_template'],
                    ['label' => 'Create a segment (optional)', 'detail' => 'Target part of the list.', 'screen' => Resources\NewsletterSegments\NewsletterSegmentResource::class, 'optional' => true],
                    ['label' => 'Write and send the campaign', 'detail' => 'Preview, test, then send or schedule.', 'screen' => Resources\NewsletterCampaigns\NewsletterCampaignResource::class],
                ],
            ],
            'broken-link' => [
                'title' => 'Fix a broken link (404)',
                'description' => 'Keep visitors and search rankings when a page moves.',
                'steps' => [
                    ['label' => 'Find the missing address', 'detail' => 'Sorted by hits; internal referrals mean a link on our own site.', 'screen' => Resources\NotFoundUrls\NotFoundUrlResource::class],
                    ['label' => 'Add a redirect', 'detail' => '301 to the right page, or 410 if it is gone for good.', 'screen' => Resources\Redirects\RedirectResource::class],
                ],
            ],
        ];
    }

    /**
     * A workflow with each step's live status and permission-aware link.
     *
     * @return array{title: string, description: string, steps: array<int, array{label: string, detail: string, url: ?string, screenLabel: ?string, status: string, count: ?int, optional: bool}>}
     */
    public static function resolve(string $key): array
    {
        $workflow = self::all()[$key];
        $nextMarked = false;

        $steps = [];

        foreach ($workflow['steps'] as $step) {
            $requirement = isset($step['requirement']) ? AdminRequirements::evaluate($step['requirement']) : null;
            $status = match (true) {
                $requirement === null => 'info',
                $requirement['met'] => 'done',
                default => 'todo',
            };

            if ($status === 'todo' && ! $nextMarked && ! ($step['optional'] ?? false)) {
                $status = 'next';
                $nextMarked = true;
            }

            $steps[] = [
                'label' => $step['label'],
                'detail' => $step['detail'],
                'url' => $step['screen'] ? AdminLinks::to($step['screen']) : null,
                'screenLabel' => $step['screen'] ? AdminLinks::label($step['screen']) : null,
                'status' => $status,
                'count' => $requirement['count'] ?? null,
                'optional' => $step['optional'] ?? false,
            ];
        }

        return [...$workflow, 'steps' => $steps];
    }
}
