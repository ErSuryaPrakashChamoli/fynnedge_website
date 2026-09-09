<?php

namespace App\Filament\Resources\PageFaqs;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\PageFaqs\Pages\CreatePageFaq;
use App\Filament\Resources\PageFaqs\Pages\EditPageFaq;
use App\Filament\Resources\PageFaqs\Pages\ListPageFaqs;
use App\Filament\Resources\PageFaqs\Schemas\PageFaqForm;
use App\Filament\Resources\PageFaqs\Tables\PageFaqsTable;
use App\Models\Faq;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The third UI over the shared `faqs` table, alongside FaqResource ("General
 * FAQs", nothing attached) and FaqsRelationManager (attached to one
 * LoanProduct/Page). This one is for FAQs pinned to whole pages by route name.
 *
 * The three stay disjoint by scope: a row here always has placements and never
 * a faqable owner, so the same FAQ can't show up in two admin lists.
 */
class PageFaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Page FAQs';

    protected static ?string $modelLabel = 'page FAQ';

    protected static ?string $pluralModelLabel = 'page FAQs';

    public static function form(Schema $schema): Schema
    {
        return PageFaqForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageFaqsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('faqable_id')
            ->whereNotNull('placements');
    }

    public static function getRelations(): array
    {
        return [
            RestorableAuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageFaqs::route('/'),
            'create' => CreatePageFaq::route('/create'),
            'edit' => EditPageFaq::route('/{record}/edit'),
        ];
    }
}
