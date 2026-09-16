<?php

namespace App\Filament\Resources\PageSeos;

use App\Filament\Resources\PageSeos\Pages\CreatePageSeo;
use App\Filament\Resources\PageSeos\Pages\EditPageSeo;
use App\Filament\Resources\PageSeos\Pages\ListPageSeos;
use App\Filament\Resources\PageSeos\Schemas\PageSeoForm;
use App\Filament\Resources\PageSeos\Tables\PageSeosTable;
use App\Models\PageSeo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Meta title and description (and the rest of the SEO section) for any URL on
 * the site, applied by App\Support\Seo\PageSeoOverrides.
 *
 * Grouped under Website Settings next to Redirects for the same reason: it is
 * site plumbing keyed by URL rather than a piece of content, and the SEO role
 * that needs it already owns that group.
 */
class PageSeoResource extends Resource
{
    protected static ?string $model = PageSeo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'Page SEO';

    protected static ?string $modelLabel = 'page SEO entry';

    protected static ?string $pluralModelLabel = 'page SEO entries';

    protected static ?string $recordTitleAttribute = 'url_path';

    public static function form(Schema $schema): Schema
    {
        return PageSeoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageSeosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSeos::route('/'),
            'create' => CreatePageSeo::route('/create'),
            'edit' => EditPageSeo::route('/{record}/edit'),
        ];
    }
}
