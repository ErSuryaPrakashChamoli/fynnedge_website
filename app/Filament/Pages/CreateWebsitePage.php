<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\CalculatorPages\CalculatorPageResource;
use App\Filament\Resources\CreditScorePages\CreditScorePageResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\LoanLandingPageSeo\LoanLandingPageSeoResource;
use App\Filament\Resources\NavigationLinks\NavigationLinkResource;
use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Filament\Resources\Pages\PageResource;
use App\Support\Admin\AdminLinks;
use App\Support\Admin\AdminRequirements;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Website Pages → Create a Page: answers "which kind of page do I need?"
 * before any form opens. Each type says what it is for, where it appears on
 * the site, how it is linked and indexed, what must exist first (live), and
 * the steps to publish — then opens that type's own editor. It creates
 * nothing itself; every page is still stored by its existing resource.
 */
class CreateWebsitePage extends Page
{
    use BelongsToAdminModule;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static ?string $navigationLabel = 'Create a Page';

    protected static ?string $title = 'Create a Page';

    protected static ?string $slug = 'create-a-page';

    protected string $view = 'filament.pages.create-website-page';

    #[Url]
    public ?string $type = null;

    /**
     * Open to anyone who can create at least one kind of page.
     */
    public static function canAccess(): bool
    {
        return collect([PageResource::class, LoanLandingPageResource::class, ArticleResource::class])
            ->contains(fn (string $resource): bool => AdminLinks::to($resource, create: true) !== null);
    }

    /**
     * @return array<string, array{title: string, icon: string, tagline: string, examples: string, address: string, appears: string, navigation: string, indexing: string, needs: array<int, string>, editor: class-string, editorQuery: array<string, string>, steps: array<int, array{label: string, detail: string, screen?: class-string}>}>
     */
    public function types(): array
    {
        return [
            'general' => [
                'title' => 'General website page',
                'icon' => 'heroicon-o-document-text',
                'tagline' => 'An information page that is not a loan offer.',
                'examples' => 'Policies, company information, a guide, an event or partnership page.',
                'address' => 'fynnedge.com/{address} — you choose the address.',
                'appears' => 'Its own page. Not added to any menu automatically.',
                'navigation' => 'Add it to the footer\'s Quick Links (Footer Quick Links) to link it from every page. The header menus are fixed.',
                'indexing' => 'Indexed by search engines and listed in the sitemap once published (set "noindex" in its SEO section to hide it).',
                'needs' => [],
                'editor' => PageResource::class,
                'editorQuery' => [],
                'steps' => [
                    ['label' => 'Title and address', 'detail' => 'The address is filled from the title; it cannot clash with an existing page.'],
                    ['label' => 'Write the body', 'detail' => 'Headings, lists, links and images in the editor.'],
                    ['label' => 'SEO section', 'detail' => 'Search title and description.'],
                    ['label' => 'Save as draft and Preview', 'detail' => 'Preview opens a private link to the draft.'],
                    ['label' => 'Publish', 'detail' => 'Set the status to Published.'],
                    ['label' => 'Add FAQs (optional)', 'detail' => 'From the page\'s FAQs tab after saving.'],
                    ['label' => 'Link it in the footer (optional)', 'detail' => 'Footer Quick Links.', 'screen' => NavigationLinkResource::class],
                ],
            ],
            'landing' => [
                'title' => 'Loan landing page',
                'icon' => 'heroicon-o-banknotes',
                'tagline' => 'A lead-generating page for one loan product.',
                'examples' => '"₹10 lakh personal loan", "Home loan for women", "Personal loan in Pune".',
                'address' => 'fynnedge.com/loans/{product}/{address}',
                'appears' => 'Includes the product\'s enquiry form, live rates and calculator, so every visit can become a lead.',
                'navigation' => 'Listed automatically in the Loans mega menu under its group (By amount, type, need or location).',
                'indexing' => 'Indexed and in the sitemap once published, while its loan product is published.',
                'needs' => ['loan_product'],
                'editor' => LoanLandingPageResource::class,
                'editorQuery' => [],
                'steps' => [
                    ['label' => 'Choose the loan product and group', 'detail' => 'Group decides where it sits in the Loans menu; amount/city fields appear for those groups.'],
                    ['label' => 'Title, address and excerpt', 'detail' => 'Write for the search you are targeting.'],
                    ['label' => 'Write the body', 'detail' => 'Rates can be inserted as live [rate:…] tokens.'],
                    ['label' => 'SEO', 'detail' => 'Search title and description.', 'screen' => LoanLandingPageSeoResource::class],
                    ['label' => 'Preview, then publish', 'detail' => 'Appears in the Loans menu as soon as it is published.'],
                    ['label' => 'Watch its leads', 'detail' => 'Lead Analytics shows the page\'s enquiries and conversion.', 'screen' => LeadAnalytics::class],
                ],
            ],
            'campaign' => [
                'title' => 'Campaign landing page',
                'icon' => 'heroicon-o-megaphone',
                'tagline' => 'A page for an ad campaign (Google/Meta ads, SMS, email).',
                'examples' => '"Diwali personal loan offer", a page used only in paid ads.',
                'address' => 'fynnedge.com/loans/{product}/{address} — a loan landing page set to noindex.',
                'appears' => 'Same lead-capturing layout as a landing page. Opens with "noindex" pre-selected so it does not compete with your SEO pages.',
                'navigation' => 'Not meant for the menus — share its address in your ads. (For a product-agnostic ad page, the fixed /quick-enquiry page is also available.)',
                'indexing' => 'Kept out of search engines and the sitemap (noindex).',
                'needs' => ['loan_product'],
                'editor' => LoanLandingPageResource::class,
                'editorQuery' => ['purpose' => 'campaign'],
                'steps' => [
                    ['label' => 'Create the landing page', 'detail' => 'SEO indexing is pre-set to "Noindex, follow".'],
                    ['label' => 'Publish it', 'detail' => 'It still appears in the Loans menu by its group — choose a group accordingly.'],
                    ['label' => 'Tag your ad links', 'detail' => 'Add ?utm_source=…&utm_campaign=… to the address in each ad.'],
                    ['label' => 'Track results', 'detail' => 'Filter Lead Analytics by campaign.', 'screen' => LeadAnalytics::class],
                ],
            ],
            'article' => [
                'title' => 'Blog article',
                'icon' => 'heroicon-o-newspaper',
                'tagline' => 'Editorial content: guides, explainers, news.',
                'examples' => '"How to improve your CIBIL score", "Home loan tax benefits".',
                'address' => 'fynnedge.com/resources/{address}',
                'appears' => 'On /resources (and optionally the homepage), with the author\'s byline and photo.',
                'navigation' => 'Listed automatically on the Resources page.',
                'indexing' => 'Indexed and in the sitemap once published.',
                'needs' => ['author'],
                'editor' => ArticleResource::class,
                'editorQuery' => [],
                'steps' => [
                    ['label' => 'Make sure the author exists', 'detail' => 'Name, photo and designation.', 'screen' => AuthorResource::class],
                    ['label' => 'Write the article', 'detail' => 'Title, category, excerpt, cover image, body; pick the author.'],
                    ['label' => 'SEO section', 'detail' => 'Search title, description, social image.'],
                    ['label' => 'Publish or schedule', 'detail' => 'Set a publish date to schedule it.'],
                    ['label' => 'Email it (optional)', 'detail' => 'Create a newsletter campaign from the article.', 'screen' => NewsletterCampaignResource::class],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selected(): ?array
    {
        $types = $this->types();

        if ($this->type === null || ! isset($types[$this->type])) {
            return null;
        }

        $type = $types[$this->type];
        $requirements = collect($type['needs'])->map(fn (string $key): ?array => AdminRequirements::evaluate($key))->filter()->values();
        $blocking = $requirements->filter(fn (array $requirement): bool => ! $requirement['met'] && $requirement['key'] !== 'author');
        $createUrl = AdminLinks::to($type['editor'], create: true);

        if ($createUrl !== null && $type['editorQuery'] !== []) {
            $createUrl .= (str_contains($createUrl, '?') ? '&' : '?').http_build_query($type['editorQuery']);
        }

        $nextMarked = false;
        $steps = [];

        foreach ($type['steps'] as $step) {
            $status = $nextMarked ? 'todo' : 'next';
            $nextMarked = true;
            $steps[] = [
                'label' => $step['label'],
                'detail' => $step['detail'],
                'url' => isset($step['screen']) ? AdminLinks::to($step['screen']) : null,
                'screenLabel' => isset($step['screen']) ? AdminLinks::label($step['screen']) : null,
                'status' => $blocking->isNotEmpty() ? 'todo' : $status,
                'count' => null,
                'optional' => str_contains($step['label'], '(optional)'),
            ];
        }

        foreach ($blocking->reverse() as $requirement) {
            array_unshift($steps, [
                'label' => $requirement['action'].' first',
                'detail' => $requirement['why'],
                'url' => $requirement['url'],
                'screenLabel' => $requirement['label'],
                'status' => 'next',
                'count' => null,
                'optional' => false,
            ]);
        }

        return [
            ...$type,
            'key' => $this->type,
            'requirements' => $requirements->all(),
            'blocked' => $blocking->isNotEmpty(),
            'createUrl' => $createUrl,
            'listUrl' => AdminLinks::to($type['editor']),
            'workflow' => ['title' => $type['title'], 'description' => '', 'steps' => $steps],
        ];
    }

    public function choose(string $type): void
    {
        $this->type = array_key_exists($type, $this->types()) ? $type : null;
    }

    /**
     * Pages that exist once and are edited, not created.
     *
     * @return array<int, array{label: string, detail: string, url: string}>
     */
    public function fixedPages(): array
    {
        return collect([
            [CalculatorPageResource::class, 'Calculator pages', 'One per calculator — edit the explanation and SEO.'],
            [CreditScorePageResource::class, 'Credit score pages', 'One per bureau — edit the content below the score check.'],
            [AboutPageSettings::class, 'About page', 'Section wording of /about.'],
            [QuickEnquiryPageSettings::class, 'Quick Enquiry page', 'The /quick-enquiry ad page\'s wording.'],
        ])
            ->map(fn (array $item): ?array => ($url = AdminLinks::to($item[0])) ? ['label' => $item[1], 'detail' => $item[2], 'url' => $url] : null)
            ->filter()
            ->values()
            ->all();
    }
}
