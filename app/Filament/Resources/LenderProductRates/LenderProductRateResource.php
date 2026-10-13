<?php

namespace App\Filament\Resources\LenderProductRates;

use App\Enums\RateStatus;
use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LenderProductRates\Pages\CreateLenderProductRate;
use App\Filament\Resources\LenderProductRates\Pages\EditLenderProductRate;
use App\Filament\Resources\LenderProductRates\Pages\ListLenderProductRates;
use App\Filament\Resources\LenderProductRates\Pages\ViewLenderProductRate;
use App\Filament\Resources\LenderProductRates\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\LenderProductRates\Schemas\LenderProductRateForm;
use App\Filament\Resources\LenderProductRates\Schemas\LenderProductRateInfolist;
use App\Filament\Resources\LenderProductRates\Tables\LenderProductRatesTable;
use App\Models\LenderProductRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog → Interest Rates: the marketing team's single place to maintain
 * every lender's published rate per loan type, with evidence, a draft →
 * approval → publish workflow and full version history. Public pages read the
 * result only through App\Support\Rates\RateBook.
 */
class LenderProductRateResource extends Resource
{
    protected static ?string $model = LenderProductRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Interest Rates';

    protected static ?string $modelLabel = 'interest rate';

    protected static ?string $pluralModelLabel = 'interest rates';

    protected static ?string $slug = 'interest-rates';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return LenderProductRateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LenderProductRateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LenderProductRatesTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof LenderProductRate ? "{$record->offerLabel()} (v{$record->version})" : 'Interest rate';
    }

    /**
     * Work waiting for a publisher, shown on the sidebar item.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = LenderProductRate::query()->where('status', RateStatus::PendingApproval)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Awaiting approval';
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLenderProductRates::route('/'),
            'create' => CreateLenderProductRate::route('/create'),
            'view' => ViewLenderProductRate::route('/{record}'),
            'edit' => EditLenderProductRate::route('/{record}/edit'),
        ];
    }
}
