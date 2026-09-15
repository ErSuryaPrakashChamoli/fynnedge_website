<?php

namespace App\Filament\Resources\PromoBars;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\PromoBars\Pages\CreatePromoBar;
use App\Filament\Resources\PromoBars\Pages\EditPromoBar;
use App\Filament\Resources\PromoBars\Pages\ListPromoBars;
use App\Filament\Resources\PromoBars\Schemas\PromoBarForm;
use App\Filament\Resources\PromoBars\Tables\PromoBarsTable;
use App\Models\PromoBar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The sticky offer bars that slide up from the bottom of public pages,
 * pinned to pages the same way Page FAQs and video testimonials are.
 * Rendered by x-site.promo-bar.
 */
class PromoBarResource extends Resource
{
    protected static ?string $model = PromoBar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Promo bars';

    protected static ?string $modelLabel = 'promo bar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PromoBarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoBarsTable::configure($table);
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
            'index' => ListPromoBars::route('/'),
            'create' => CreatePromoBar::route('/create'),
            'edit' => EditPromoBar::route('/{record}/edit'),
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
