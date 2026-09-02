<?php

namespace App\Filament\Resources\LoanLandingPageSeo;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LoanLandingPageSeo\Pages\EditLoanLandingPageSeo;
use App\Filament\Resources\LoanLandingPageSeo\Pages\ListLoanLandingPageSeo;
use App\Filament\Resources\LoanLandingPageSeo\Schemas\LoanLandingPageSeoForm;
use App\Filament\Resources\LoanLandingPageSeo\Tables\LoanLandingPageSeoTable;
use App\Models\LoanLandingPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 9A — the SEO-role counterpart to LoanLandingPageContentResource: a
 * restricted editing boundary onto the same LoanLandingPage record, exposing
 * only the existing SeoFormSection. SEO previously had blanket
 * Update:LoanLandingPage access (the whole form, including loan_product_id,
 * group, slug and amount) — this replaces that with a permission
 * (ViewAny/View/Update:LoanLandingPageSeo) scoped to exactly what the role
 * name implies. See LoanLandingPageContentResource's docblock for why
 * authorization is implemented via explicit overrides rather than Filament's
 * default Policy resolution.
 */
class LoanLandingPageSeoResource extends Resource
{
    protected static ?string $model = LoanLandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Loan Landing Page SEO';

    protected static ?string $modelLabel = 'loan landing page SEO';

    protected static ?string $slug = 'loan-landing-page-seo';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:LoanLandingPageSeo');
    }

    public static function canView(Model $record): bool
    {
        return (bool) auth()->user()?->can('View:LoanLandingPageSeo');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('Update:LoanLandingPageSeo');
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
        return LoanLandingPageSeoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanLandingPageSeoTable::configure($table);
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
            'index' => ListLoanLandingPageSeo::route('/'),
            'edit' => EditLoanLandingPageSeo::route('/{record}/edit'),
        ];
    }
}
