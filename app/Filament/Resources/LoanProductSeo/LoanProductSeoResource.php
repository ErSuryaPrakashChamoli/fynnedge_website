<?php

namespace App\Filament\Resources\LoanProductSeo;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LoanProductSeo\Pages\EditLoanProductSeo;
use App\Filament\Resources\LoanProductSeo\Pages\ListLoanProductSeo;
use App\Filament\Resources\LoanProductSeo\Schemas\LoanProductSeoForm;
use App\Filament\Resources\LoanProductSeo\Tables\LoanProductSeoTable;
use App\Models\LoanProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 8 — the SEO-role counterpart to LoanProductContentResource: a
 * restricted editing boundary onto the same LoanProduct record, exposing
 * only the existing SeoFormSection. SEO previously had blanket
 * Update:LoanProduct access (the whole form, including calculator/financial
 * fields) — this replaces that with a permission
 * (ViewAny/View/Update:LoanProductSeo) scoped to exactly what the role name
 * implies. See LoanProductContentResource's docblock for why authorization
 * is implemented via explicit overrides rather than Filament's default
 * Policy resolution.
 */
class LoanProductSeoResource extends Resource
{
    protected static ?string $model = LoanProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Loan Product SEO';

    protected static ?string $modelLabel = 'loan product SEO';

    protected static ?string $slug = 'loan-product-seo';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:LoanProductSeo');
    }

    public static function canView(Model $record): bool
    {
        return (bool) auth()->user()?->can('View:LoanProductSeo');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('Update:LoanProductSeo');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return LoanProductSeoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanProductSeoTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoanProductSeo::route('/'),
            'edit' => EditLoanProductSeo::route('/{record}/edit'),
        ];
    }
}
