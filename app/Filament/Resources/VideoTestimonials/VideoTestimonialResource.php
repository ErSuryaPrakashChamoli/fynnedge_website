<?php

namespace App\Filament\Resources\VideoTestimonials;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\VideoTestimonials\Pages\CreateVideoTestimonial;
use App\Filament\Resources\VideoTestimonials\Pages\EditVideoTestimonial;
use App\Filament\Resources\VideoTestimonials\Pages\ListVideoTestimonials;
use App\Filament\Resources\VideoTestimonials\Schemas\VideoTestimonialForm;
use App\Filament\Resources\VideoTestimonials\Tables\VideoTestimonialsTable;
use App\Models\VideoTestimonial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Customer video testimonials, pinned to pages the same way Page FAQs are.
 * Rendered by x-site.video-testimonials (a card carousel), and optionally
 * x-site.video-testimonial-bubble (the floating corner preview).
 */
class VideoTestimonialResource extends Resource
{
    protected static ?string $model = VideoTestimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Video testimonials';

    protected static ?string $recordTitleAttribute = 'customer_name';

    public static function form(Schema $schema): Schema
    {
        return VideoTestimonialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VideoTestimonialsTable::configure($table);
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
            'index' => ListVideoTestimonials::route('/'),
            'create' => CreateVideoTestimonial::route('/create'),
            'edit' => EditVideoTestimonial::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
