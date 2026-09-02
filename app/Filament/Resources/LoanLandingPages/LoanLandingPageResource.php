<?php

namespace App\Filament\Resources\LoanLandingPages;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\LoanLandingPages\Pages\CreateLoanLandingPage;
use App\Filament\Resources\LoanLandingPages\Pages\EditLoanLandingPage;
use App\Filament\Resources\LoanLandingPages\Pages\ListLoanLandingPages;
use App\Filament\Resources\LoanLandingPages\Schemas\LoanLandingPageForm;
use App\Filament\Resources\LoanLandingPages\Tables\LoanLandingPagesTable;
use App\Models\LoanLandingPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanLandingPageResource extends Resource
{
    protected static ?string $model = LoanLandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Loan Landing Pages';

    public static function form(Schema $schema): Schema
    {
        return LoanLandingPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanLandingPagesTable::configure($table);
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
            'index' => ListLoanLandingPages::route('/'),
            'create' => CreateLoanLandingPage::route('/create'),
            'edit' => EditLoanLandingPage::route('/{record}/edit'),
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
