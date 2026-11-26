<?php

namespace App\Filament\Clusters;

use App\Filament\Clusters\Administration\AdministrationCluster;
use App\Filament\Clusters\Content\ContentCluster;
use App\Filament\Clusters\CreditBureau\CreditBureauCluster;
use App\Filament\Clusters\DebtConsolidation\DebtConsolidationCluster;
use App\Filament\Clusters\Leads\LeadsCluster;
use App\Filament\Clusters\LoanCatalog\LoanCatalogCluster;
use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Clusters\Seo\SeoCluster;
use App\Filament\Clusters\SiteSettings\SiteSettingsCluster;
use App\Filament\Clusters\WebsitePages\WebsitePagesCluster;
use App\Filament\Pages;
use App\Filament\Resources;

/**
 * The admin panel's information architecture, in one place: which main module
 * (sidebar item) every screen belongs to, which dropdown tab it sits under
 * inside that module's top bar (null = its own tab), its order, and — only
 * where the old name was ambiguous — a clearer tab label.
 *
 * A screen joins a module by using {@see Concerns\BelongsToAdminModule}; its
 * row here is then the single source of truth for group/sort/label. Changing
 * a row never changes a URL (see AdminModuleCluster).
 */
final class AdminModules
{
    /**
     * Main modules in sidebar order.
     *
     * @return array<int, class-string<AdminModuleCluster>>
     */
    public static function clusters(): array
    {
        return [
            LeadsCluster::class,
            DebtConsolidationCluster::class,
            LoanCatalogCluster::class,
            CreditBureauCluster::class,
            WebsitePagesCluster::class,
            ContentCluster::class,
            SeoCluster::class,
            MarketingCluster::class,
            SiteSettingsCluster::class,
            AdministrationCluster::class,
        ];
    }

    /**
     * @return array<class-string, array{cluster: class-string<AdminModuleCluster>, group: ?string, sort: ?int, label?: string}>
     */
    public static function placements(): array
    {
        return [
            // Leads & Enquiries — everything a visitor submits, plus where it came from and where it went.
            Pages\LeadInbox::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 1],
            Resources\ContactEnquiries\ContactEnquiryResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 10, 'label' => 'Enquiries'],
            Resources\MobileOtpChallenges\MobileOtpChallengeResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 20],
            Resources\CreditScoreChecks\CreditScoreCheckResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 30],
            Resources\JourneySessions\JourneySessionResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 40],
            Resources\Applications\ApplicationResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 50],
            Resources\Customers\CustomerResource::class => ['cluster' => LeadsCluster::class, 'group' => null, 'sort' => 60],
            Pages\LeadAnalytics::class => ['cluster' => LeadsCluster::class, 'group' => 'Analytics & LMS', 'sort' => 70],
            Pages\AnalyticsDashboard::class => ['cluster' => LeadsCluster::class, 'group' => 'Analytics & LMS', 'sort' => 71],
            Pages\FynnOnSync::class => ['cluster' => LeadsCluster::class, 'group' => 'Analytics & LMS', 'sort' => 72],

            // Debt Consolidation — cases from first enquiry to verified closure of the old loans.
            Pages\DebtConsolidationDashboard::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 1],
            Pages\DebtConsolidationEnquiries::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 5],
            Resources\DebtApplications\DebtApplicationResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 10, 'label' => 'Applications'],
            Pages\DebtConsolidationCustomers::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 20],
            Resources\DebtLiabilities\DebtLiabilityResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Case work', 'sort' => 30],
            Resources\DebtCalculationSnapshots\DebtCalculationSnapshotResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Case work', 'sort' => 31],
            Resources\DebtForeclosureQuotations\DebtForeclosureQuotationResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Case work', 'sort' => 32],
            Resources\DebtScenarios\DebtScenarioResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Case work', 'sort' => 33],
            Resources\DebtLenderMatches\DebtLenderMatchResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Lenders & closure', 'sort' => 40],
            Resources\DebtLenderApplications\DebtLenderApplicationResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Lenders & closure', 'sort' => 41],
            Resources\DebtClosures\DebtClosureResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => 'Lenders & closure', 'sort' => 42],
            Resources\DebtReports\DebtReportResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 50],
            Resources\DebtScoringConfigs\DebtScoringConfigResource::class => ['cluster' => DebtConsolidationCluster::class, 'group' => null, 'sort' => 60],

            // Loan Products & Lenders — business configuration, not page copy.
            Resources\LoanProducts\LoanProductResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => null, 'sort' => 10],
            Resources\Lenders\LenderResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => null, 'sort' => 20],
            Resources\LenderProducts\LenderProductResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => null, 'sort' => 30],
            Resources\LenderProductRates\LenderProductRateResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => null, 'sort' => 40],
            Resources\EligibilityRuleSets\EligibilityRuleSetResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Eligibility', 'sort' => 50],
            Pages\EligibilityTester::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Eligibility', 'sort' => 51],
            Resources\Employers\EmployerResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Eligibility', 'sort' => 52],
            Resources\DebtLenderPolicies\DebtLenderPolicyResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Eligibility', 'sort' => 53],
            Resources\JourneyDefinitions\JourneyDefinitionResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Application setup', 'sort' => 60],
            Resources\DocumentTypes\DocumentTypeResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Application setup', 'sort' => 61],
            Resources\DocumentRequirements\DocumentRequirementResource::class => ['cluster' => LoanCatalogCluster::class, 'group' => 'Application setup', 'sort' => 62],

            // Credit Bureau — one dropdown tab per bureau. Sorts inside each bureau are its own
            // (null keeps them); the dashboards' negative sorts fix the tab order CIBIL → CRIF.
            Pages\CibilDashboard::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CIBIL', 'sort' => -4],
            Resources\CibilCreditRequests\CibilCreditRequestResource::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CIBIL', 'sort' => null],
            Pages\CibilSettings::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CIBIL', 'sort' => null],
            Pages\CibilAuditHistory::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CIBIL', 'sort' => null],
            Pages\ExperianDashboard::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Experian', 'sort' => -3],
            Resources\ExperianCreditRequests\ExperianCreditRequestResource::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Experian', 'sort' => null],
            Pages\ExperianSettings::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Experian', 'sort' => null],
            Pages\ExperianAuditHistory::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Experian', 'sort' => null],
            Pages\EquifaxDashboard::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Equifax', 'sort' => -2],
            Resources\EquifaxCreditRequests\EquifaxCreditRequestResource::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Equifax', 'sort' => null],
            Pages\EquifaxSettings::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Equifax', 'sort' => null],
            Pages\EquifaxAuditHistory::class => ['cluster' => CreditBureauCluster::class, 'group' => 'Equifax', 'sort' => null],
            Pages\CrifDashboard::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CRIF', 'sort' => -1],
            Resources\CrifReports\CrifReportResource::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CRIF', 'sort' => null],
            Pages\CrifSettings::class => ['cluster' => CreditBureauCluster::class, 'group' => 'CRIF', 'sort' => null],

            // Website Pages — every kind of public page, side by side, so "which one do I use?"
            // is answered by the Create a Page guide on the first tab.
            Pages\CreateWebsitePage::class => ['cluster' => WebsitePagesCluster::class, 'group' => null, 'sort' => 1],
            Resources\Pages\PageResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => null, 'sort' => 10, 'label' => 'General Pages'],
            Resources\LoanLandingPages\LoanLandingPageResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => null, 'sort' => 20],
            Resources\LoanLandingPageContent\LoanLandingPageContentResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => null, 'sort' => 30],
            Resources\LoanProductContent\LoanProductContentResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => null, 'sort' => 40],
            Resources\CalculatorPages\CalculatorPageResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Fixed pages', 'sort' => 50],
            Resources\CreditScorePages\CreditScorePageResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Fixed pages', 'sort' => 51],
            Resources\Banners\BannerResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Homepage & sections', 'sort' => 60],
            Resources\MarketingSections\MarketingSectionResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Homepage & sections', 'sort' => 61],
            Resources\PromoBars\PromoBarResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Homepage & sections', 'sort' => 62],
            Resources\NavigationLinks\NavigationLinkResource::class => ['cluster' => WebsitePagesCluster::class, 'group' => 'Homepage & sections', 'sort' => 63, 'label' => 'Footer Quick Links'],

            // Blog & Content — editorial content and the people/media behind it.
            Resources\Articles\ArticleResource::class => ['cluster' => ContentCluster::class, 'group' => null, 'sort' => 10],
            Resources\Authors\AuthorResource::class => ['cluster' => ContentCluster::class, 'group' => null, 'sort' => 20],
            Resources\Faqs\FaqResource::class => ['cluster' => ContentCluster::class, 'group' => null, 'sort' => 30],
            Resources\PageFaqs\PageFaqResource::class => ['cluster' => ContentCluster::class, 'group' => null, 'sort' => 31],
            Pages\MediaGovernance::class => ['cluster' => ContentCluster::class, 'group' => null, 'sort' => 40],
            Resources\Testimonials\TestimonialResource::class => ['cluster' => ContentCluster::class, 'group' => 'Social proof', 'sort' => 50],
            Resources\VideoTestimonials\VideoTestimonialResource::class => ['cluster' => ContentCluster::class, 'group' => 'Social proof', 'sort' => 51],
            Resources\SocialVideos\SocialVideoResource::class => ['cluster' => ContentCluster::class, 'group' => 'Social proof', 'sort' => 52],
            Resources\Achievements\AchievementResource::class => ['cluster' => ContentCluster::class, 'group' => 'Social proof', 'sort' => 53],
            Resources\CompanyPhotos\CompanyPhotoResource::class => ['cluster' => ContentCluster::class, 'group' => 'Social proof', 'sort' => 54],

            // SEO & Performance — everything search engines and trackers read.
            Pages\SeoAnalytics::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 10],
            Resources\PageSeos\PageSeoResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 20],
            Resources\LoanProductSeo\LoanProductSeoResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 30],
            Resources\LoanLandingPageSeo\LoanLandingPageSeoResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 31],
            Pages\StructuredData::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 40],
            Resources\SchemaTemplates\SchemaTemplateResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 41],
            Resources\Redirects\RedirectResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 50],
            Resources\NotFoundUrls\NotFoundUrlResource::class => ['cluster' => SeoCluster::class, 'group' => null, 'sort' => 51],

            // Website Settings — site-wide values and the wording of fixed pages.
            Pages\Settings::class => ['cluster' => SiteSettingsCluster::class, 'group' => null, 'sort' => 10, 'label' => 'General Settings'],
            Pages\AboutPageSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => null, 'sort' => 20],
            Pages\QuickEnquiryPageSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => null, 'sort' => 30],
            Pages\CalculatorPagesSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => null, 'sort' => 40],
            Pages\CreditScorePageSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => null, 'sort' => 50],
            Pages\DebtConsolidationSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => 'Debt Consolidation', 'sort' => 55],
            Pages\DebtConsolidationPagesSettings::class => ['cluster' => SiteSettingsCluster::class, 'group' => 'Debt Consolidation', 'sort' => 56],
            Resources\HowItWorksSteps\HowItWorksStepResource::class => ['cluster' => SiteSettingsCluster::class, 'group' => 'Company information', 'sort' => 60],
            Resources\GrievanceLevels\GrievanceLevelResource::class => ['cluster' => SiteSettingsCluster::class, 'group' => 'Company information', 'sort' => 61],
            Resources\JobOpenings\JobOpeningResource::class => ['cluster' => SiteSettingsCluster::class, 'group' => 'Company information', 'sort' => 62],

            // Email Marketing — the newsletter, in the order a campaign is built.
            Pages\NewsletterDashboard::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 1],
            Resources\NewsletterSubscribers\NewsletterSubscriberResource::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 2],
            Resources\NewsletterCampaigns\NewsletterCampaignResource::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 3],
            Resources\NewsletterTemplates\NewsletterTemplateResource::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 4],
            Resources\NewsletterSegments\NewsletterSegmentResource::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 5],
            Pages\NewsletterSettings::class => ['cluster' => MarketingCluster::class, 'group' => null, 'sort' => 6],

            // Administration — who can sign in and what they may do. Roles (Filament Shield's
            // own resource) joins through config/filament-shield.php, not this list.
            Resources\Users\UserResource::class => ['cluster' => AdministrationCluster::class, 'group' => null, 'sort' => 10],
            Resources\ApiClients\ApiClientResource::class => ['cluster' => AdministrationCluster::class, 'group' => null, 'sort' => 30],
            Pages\AdminActivity::class => ['cluster' => AdministrationCluster::class, 'group' => null, 'sort' => 40],
        ];
    }

    /**
     * @return array{cluster: class-string<AdminModuleCluster>, group: ?string, sort: ?int, label?: string}|null
     */
    public static function placementFor(string $component): ?array
    {
        return self::placements()[$component] ?? null;
    }
}
