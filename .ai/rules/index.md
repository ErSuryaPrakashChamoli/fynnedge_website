# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Models/Achievement.php,app/Filament/Resources/Achievements/**,resources/views/components/site/hero-stats.blade.php | .ai/rules/achievements-views-components-site.md |
| app/Models/Achievement.php,app/Filament/Resources/Achievements/**,resources/views/home.blade.php | .ai/rules/achievements-views.md |
| app/Support/Privacy/**,app/Support/Analytics/**,resources/views/components/site/cookie-consent.blade.php | .ai/rules/analytics-views-components-site.md |
| app/Filament/**/FileUpload*,config/filesystems.php | .ai/rules/app-filament.md |
| app/Providers/Filament/AdminPanelProvider.php | .ai/rules/app-providers-filament.md |
| app/** | .ai/rules/app.md |
| app/Support/Loans/LoanMegaMenu.php,app/Support/Calculators/CalculatorCatalog.php,app/Models/NavigationLink.php | .ai/rules/calculators-models.md |
| app/Support/Seo/SeoDefaults.php,app/Support/Seo/Sitemap.php,app/Support/Seo/CrawlerPolicy.php,resources/views/components/layouts/app.blade.php | .ai/rules/components-layouts.md |
| app/Models/MarketingSection.php,app/Models/NavigationLink.php,resources/views/home.blade.php,resources/views/components/site/footer.blade.php | .ai/rules/components-site.md |
| app/Support/Calculators/**,resources/views/components/⚡*.blade.php, app/Support/Calculators/**,resources/views/components/⚡emi-calculator.blade.php | .ai/rules/components.md |
| app/Filament/RelationManagers/**,app/Filament/Concerns/** | .ai/rules/concerns.md |
| config/filesystems.php,app/Filament/**/*Form.php,docker/entrypoint.sh | .ai/rules/config-filament.md |
| config/database.php,.env*, config/cache.php,config/queue.php,config/session.php,.env*, config/{cache,queue,session}.php | .ai/rules/config.md |
| app/Models/ContactEnquiry.php,app/Modules/Enquiries/**,app/Http/Controllers/QuickEnquiryController.php,app/Http/Controllers/LoanEnquiryController.php,app/Support/Enquiries/**,app/Filament/Resources/ContactEnquiries/**,resources/views/components/site/loan-enquiry*.blade.php | .ai/rules/contact-enquiries.md |
| app/Filament/RelationManagers/FaqsRelationManager.php,app/Models/Page.php,app/Http/Controllers/PageController.php | .ai/rules/controllers.md |
| resources/views/emails/**,resources/views/components/emails/** | .ai/rules/emails.md |
| app/Support/Media/**,app/Filament/Pages/MediaGovernance.php | .ai/rules/filament-pages.md |
| app/Models/Concerns/Publishable.php,app/Filament/Resources/** | .ai/rules/filament-resources.md |
| app/Models/User.php,app/Filament/**,config/filament-shield.php,config/permission.php,database/seeders/RoleSeeder.php | .ai/rules/filament-seeders.md |
| app/Enums/**,app/Filament/** | .ai/rules/filament.md |
| app/Modules/Newsletter/**,app/Mail/Newsletter/**,app/Http/Controllers/NewsletterController.php | .ai/rules/http-controllers.md |
| app/Support/Analytics/**,app/Filament/Pages/SeoAnalytics.php,app/Http/Middleware/SecurityHeaders.php | .ai/rules/http-middleware.md |
| app/Http/Controllers/JourneyController.php,app/Modules/Journey/** | .ai/rules/journey.md |
| app/Support/Seo/**,resources/views/components/layouts/app.blade.php,public/robots.txt,resources/views/sitemap.blade.php | .ai/rules/layouts-views.md |
| app/Filament/Schemas/SeoFormSection.php,app/Models/SeoMeta.php,app/Models/Concerns/Seoable.php,resources/views/components/layouts/app.blade.php | .ai/rules/layouts.md |
| app/Filament/Resources/Faqs/**,app/Filament/Resources/LoanProducts/** | .ai/rules/loan-products.md |
| app/Support/Seo/**,app/Filament/Pages/Settings.php,resources/views/loans/** | .ai/rules/loans.md |
| app/Http/Middleware/SecurityHeaders.php | .ai/rules/middleware.md |
| database/migrations/** | .ai/rules/migrations.md |
| app/Models/**,app/Filament/** | .ai/rules/models-filament.md |
| app/Modules/**/Models/*.php | .ai/rules/models.md |
| app/Models/**,app/Modules/**/Models/*.php | .ai/rules/modules-models.md |
| app/Http/Controllers/RobotsController.php,app/Http/Controllers/SitemapController.php,app/Support/Seo/SearchEngineIndexing.php,docker/nginx/nginx.conf | .ai/rules/nginx.md |
| app/Enums/FaqPlacement.php,app/Support/Faqs/**,app/Filament/Resources/PageFaqs/**,resources/views/components/site/page-faqs.blade.php | .ai/rules/page-faqs-views-components-site.md |
| app/Filament/Pages/** | .ai/rules/pages.md |
| app/Http/Middleware/SecurityHeaders.php,app/Providers/Filament/AdminPanelProvider.php | .ai/rules/providers-filament.md |
| app/Models/Redirect.php,app/Http/Middleware/HandleRedirects.php,app/Filament/Resources/Redirects/** | .ai/rules/redirects.md |
| app/Filament/Resources/** | .ai/rules/resources.md |
| app/Support/Seo/**,app/Filament/Pages/StructuredData.php,app/Filament/Resources/SchemaTemplates/**,app/Filament/Schemas/SeoFormSection.php | .ai/rules/schemas.md |
| app/Support/Seo/OrganizationSchema.php,database/seeders/BusinessProfileSeeder.php,app/Filament/Pages/Settings.php | .ai/rules/seeders-filament-pages.md |
| database/seeders/FlexiHybridTermLoanSeeder.php,database/seeders/JourneySeeder.php | .ai/rules/seeders-seeders.md |
| database/seeders/**, database/seeders/LegalPageSeeder.php | .ai/rules/seeders.md |
| app/Models/MarketingSection.php,app/Models/NavigationLink.php,resources/views/home.blade.php,resources/views/components/site/footer.blade.php,resources/views/components/site/flexi-hybrid-ticker.blade.php | .ai/rules/site-views-components-site.md |
| resources/views/components/site/nav-link.blade.php | .ai/rules/site.md |
| tests/** | .ai/rules/tests.md |
| resources/css/**,resources/views/**,app/Support/Theme/**,app/Filament/Pages/Settings.php | .ai/rules/theme-filament-pages.md |
| app/Support/Options/**,resources/views/components/ui/searchable-select.blade.php | .ai/rules/ui.md |
| config/filesystems.php,resources/views/components/layouts/app.blade.php,tests/** | .ai/rules/views-components-layouts.md |
| app/Support/Calculators/**,app/Http/Controllers/LoanProductController.php,resources/views/loans/**,resources/views/components/site/flexi-hybrid-hero.blade.php | .ai/rules/views-components-site.md |
| resources/css/**,resources/views/** | .ai/rules/views.md |
