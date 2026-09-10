<?php

namespace App\Filament\Resources\NewsletterTemplates;

use App\Filament\Resources\NewsletterTemplates\Pages\CreateNewsletterTemplate;
use App\Filament\Resources\NewsletterTemplates\Pages\EditNewsletterTemplate;
use App\Filament\Resources\NewsletterTemplates\Pages\ListNewsletterTemplates;
use App\Filament\Resources\NewsletterTemplates\Schemas\NewsletterTemplateForm;
use App\Filament\Resources\NewsletterTemplates\Tables\NewsletterTemplatesTable;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsletterTemplateResource extends Resource
{
    protected static ?string $model = NewsletterTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return NewsletterTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterTemplatesTable::configure($table);
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
            'index' => ListNewsletterTemplates::route('/'),
            'create' => CreateNewsletterTemplate::route('/create'),
            'edit' => EditNewsletterTemplate::route('/{record}/edit'),
        ];
    }
}
