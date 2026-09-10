<?php

namespace App\Filament\Resources\NewsletterSegments;

use App\Filament\Resources\NewsletterSegments\Pages\CreateNewsletterSegment;
use App\Filament\Resources\NewsletterSegments\Pages\EditNewsletterSegment;
use App\Filament\Resources\NewsletterSegments\Pages\ListNewsletterSegments;
use App\Filament\Resources\NewsletterSegments\Schemas\NewsletterSegmentForm;
use App\Filament\Resources\NewsletterSegments\Tables\NewsletterSegmentsTable;
use App\Modules\Newsletter\Models\NewsletterSegment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsletterSegmentResource extends Resource
{
    protected static ?string $model = NewsletterSegment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Segments';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return NewsletterSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSegmentsTable::configure($table);
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
            'index' => ListNewsletterSegments::route('/'),
            'create' => CreateNewsletterSegment::route('/create'),
            'edit' => EditNewsletterSegment::route('/{record}/edit'),
        ];
    }
}
