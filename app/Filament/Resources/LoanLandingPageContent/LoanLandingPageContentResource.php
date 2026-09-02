<?php

namespace App\Filament\Resources\LoanLandingPageContent;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LoanLandingPageContent\Pages\EditLoanLandingPageContent;
use App\Filament\Resources\LoanLandingPageContent\Pages\ListLoanLandingPageContent;
use App\Filament\Resources\LoanLandingPageContent\Schemas\LoanLandingPageContentForm;
use App\Filament\Resources\LoanLandingPageContent\Tables\LoanLandingPageContentTable;
use App\Models\LoanLandingPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 9A — the LoanLandingPage counterpart to LoanProductContentResource
 * (Phase 8): a restricted editing boundary onto the SAME LoanLandingPage
 * record the full LoanLandingPageResource manages, exposing only website
 * marketing content (see LoanLandingPageContentForm). Not a second model or
 * table — a second door onto the same one, guarded by its own permission
 * (ViewAny/View/Update:LoanLandingPageContent) independent of
 * Update:LoanLandingPage, which also covers loan_product_id, group, slug and
 * amount — business/routing fields Marketing was never meant to change.
 *
 * Authorization bypasses Filament's default Gate::getPolicyFor() resolution
 * (which would resolve to LoanLandingPagePolicy and therefore
 * Update:LoanLandingPage, defeating the boundary) via explicit can*()
 * overrides, exactly as LoanProductContentResource does. See
 * RestrictsUpdateToAllowedFields for the server-side field allowlist that
 * backs this up regardless of what the form renders.
 */
class LoanLandingPageContentResource extends Resource
{
    protected static ?string $model = LoanLandingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Loan Landing Page Content';

    protected static ?string $modelLabel = 'loan landing page content';

    protected static ?string $slug = 'loan-landing-page-content';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:LoanLandingPageContent');
    }

    public static function canView(Model $record): bool
    {
        return (bool) auth()->user()?->can('View:LoanLandingPageContent');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('Update:LoanLandingPageContent');
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
        return LoanLandingPageContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanLandingPageContentTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Read-only History only — never Restorable. A single audit row
            // can mix a marketing edit with an admin's business-field edit
            // (e.g. one save changing both title and loan_product_id);
            // restoring it would revert both. Same reasoning as
            // LoanProductContentResource's exclusion.
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoanLandingPageContent::route('/'),
            'edit' => EditLoanLandingPageContent::route('/{record}/edit'),
        ];
    }
}
