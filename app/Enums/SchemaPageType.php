<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The schema.org WebPage subtypes an admin can assign to a page's `WebPage`
 * node in the sitewide `@graph` (see App\Support\Seo\SchemaGraph::webPage()).
 *
 * Curated rather than free text: `@type` is the one property in the graph that
 * changes how a crawler interprets every other property on the node, so a typo
 * silently downgrades the page to an unrecognised type. Templates
 * (App\Models\SchemaTemplate) are where an admin declares arbitrary types.
 *
 * FAQPage is deliberately absent. It is generated from real Faq records into
 * its own <script> block beside the visible accordion, and declaring it here
 * too would double-declare the same entity — the rule PageFaqSchemaTest pins.
 */
enum SchemaPageType: string implements HasLabel
{
    case WebPage = 'WebPage';
    case AboutPage = 'AboutPage';
    case ContactPage = 'ContactPage';
    case CollectionPage = 'CollectionPage';
    case ItemPage = 'ItemPage';
    case ProfilePage = 'ProfilePage';
    case QAPage = 'QAPage';
    case SearchResultsPage = 'SearchResultsPage';

    public function getLabel(): string
    {
        return match ($this) {
            self::WebPage => 'WebPage — a general page',
            self::AboutPage => 'AboutPage — about the business',
            self::ContactPage => 'ContactPage — contact details',
            self::CollectionPage => 'CollectionPage — a list or directory',
            self::ItemPage => 'ItemPage — one specific item',
            self::ProfilePage => 'ProfilePage — a person or entity profile',
            self::QAPage => 'QAPage — a single question and its answers',
            self::SearchResultsPage => 'SearchResultsPage — search output',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getLabel()])
            ->all();
    }
}
