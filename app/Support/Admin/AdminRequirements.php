<?php

namespace App\Support\Admin;

use App\Filament\Pages\FynnOnSync;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\DocumentRequirements\DocumentRequirementResource;
use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use App\Filament\Resources\EligibilityRuleSets\EligibilityRuleSetResource;
use App\Filament\Resources\JourneyDefinitions\JourneyDefinitionResource;
use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Filament\Resources\LenderProducts\LenderProductResource;
use App\Filament\Resources\Lenders\LenderResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\LoanProducts\LoanProductResource;
use App\Filament\Resources\NewsletterTemplates\NewsletterTemplateResource;
use App\Models\Author;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Modules\FynnOn\Services\FynnOnSettings;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use Closure;

/**
 * Things a screen needs to exist before it is useful — "a landing page needs a
 * loan product", "a campaign needs a template" — each with a live count, so
 * the help panel and the Admin Guide show what is done and what is missing
 * instead of a static checklist.
 *
 * Counts are plain existence counts (no record data), so showing them to any
 * signed-in admin reveals nothing a screen's own permissions protect.
 */
final class AdminRequirements
{
    /**
     * @return array<string, array{label: string, why: string, count: Closure(): int, configure: class-string, action: string}>
     */
    public static function all(): array
    {
        return [
            'lender' => [
                'label' => 'Lenders',
                'why' => 'Offers, rates, eligibility rules and comparison tables all belong to a lender (bank or NBFC).',
                'count' => fn (): int => Lender::query()->count(),
                'configure' => LenderResource::class,
                'action' => 'Add a lender',
            ],
            'loan_product' => [
                'label' => 'Loan products',
                'why' => 'Every loan page, landing page, journey and enquiry form belongs to one loan product (Personal Loan, Home Loan…).',
                'count' => fn (): int => LoanProduct::query()->count(),
                'configure' => LoanProductResource::class,
                'action' => 'Create a loan product',
            ],
            'published_loan_product' => [
                'label' => 'Published loan products',
                'why' => 'A landing page is only reachable while its loan product is published — its URL is /loans/{product}/{page}.',
                'count' => fn (): int => LoanProduct::query()->published()->count(),
                'configure' => LoanProductResource::class,
                'action' => 'Publish a loan product',
            ],
            'lender_offer' => [
                'label' => 'Lender offers',
                'why' => 'Interest rates, eligibility rules and document requirements are set per lender offer (one lender × one loan product).',
                'count' => fn (): int => LenderProduct::query()->count(),
                'configure' => LenderProductResource::class,
                'action' => 'Add a lender offer',
            ],
            'interest_rate' => [
                'label' => 'Published interest rates',
                'why' => 'Loan pages show "starting at" rates and rate tables only from published rates.',
                'count' => fn (): int => LenderProductRate::query()->published()->count(),
                'configure' => LenderProductRateResource::class,
                'action' => 'Publish interest rates',
            ],
            'document_requirement' => [
                'label' => 'Document requirements',
                'why' => 'After choosing a lender, applicants see the upload checklist defined here.',
                'count' => fn (): int => LenderProductDocumentRequirement::query()->count(),
                'configure' => DocumentRequirementResource::class,
                'action' => 'Set document requirements',
            ],
            'document_type' => [
                'label' => 'Document types',
                'why' => 'Document requirements pick from this list (PAN, salary slips, bank statements…).',
                'count' => fn (): int => DocumentType::query()->count(),
                'configure' => DocumentTypeResource::class,
                'action' => 'Add a document type',
            ],
            'eligibility_rules' => [
                'label' => 'Eligibility rule sets',
                'why' => 'The eligibility check and the Eligibility Tester can only judge lenders that have rules.',
                'count' => fn (): int => EligibilityRuleSet::query()->count(),
                'configure' => EligibilityRuleSetResource::class,
                'action' => 'Add eligibility rules',
            ],
            'journey' => [
                'label' => 'Application journeys',
                'why' => 'The "Apply" button on a loan page starts that product\'s journey — without one there is no online application.',
                'count' => fn (): int => JourneyDefinition::query()->count(),
                'configure' => JourneyDefinitionResource::class,
                'action' => 'Set up a journey',
            ],
            'landing_page' => [
                'label' => 'Loan landing pages',
                'why' => 'Landing page content and SEO edit an existing landing page; they cannot create one.',
                'count' => fn (): int => LoanLandingPage::query()->count(),
                'configure' => LoanLandingPageResource::class,
                'action' => 'Create a landing page',
            ],
            'newsletter_template' => [
                'label' => 'Newsletter templates',
                'why' => 'A campaign is written into a template (the email layout).',
                'count' => fn (): int => NewsletterTemplate::query()->count(),
                'configure' => NewsletterTemplateResource::class,
                'action' => 'Create a template',
            ],
            'author' => [
                'label' => 'Article authors',
                'why' => 'Articles show the selected author\'s name, photo and designation as a byline. Optional, but recommended for trust and SEO.',
                'count' => fn (): int => Author::query()->count(),
                'configure' => AuthorResource::class,
                'action' => 'Add an author',
            ],
            'fynnon_connection' => [
                'label' => 'FynnOn LMS connection',
                'why' => 'New leads are pushed to FynnOn LMS only once its address and key are configured on the server.',
                'count' => fn (): int => FynnOnSettings::isConfigured() ? 1 : 0,
                'configure' => FynnOnSync::class,
                'action' => 'See connection status',
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, why: string, count: int, met: bool, url: ?string, action: string}|null
     */
    public static function evaluate(string $key): ?array
    {
        $requirement = self::all()[$key] ?? null;

        if ($requirement === null) {
            return null;
        }

        $count = ($requirement['count'])();

        return [
            'key' => $key,
            'label' => $requirement['label'],
            'why' => $requirement['why'],
            'count' => $count,
            'met' => $count > 0,
            'url' => AdminLinks::to($requirement['configure']),
            'action' => $requirement['action'],
        ];
    }
}
