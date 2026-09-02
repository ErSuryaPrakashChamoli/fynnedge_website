<?php

namespace App\Filament\Resources\LoanProductContent;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LoanProductContent\Pages\EditLoanProductContent;
use App\Filament\Resources\LoanProductContent\Pages\ListLoanProductContent;
use App\Filament\Resources\LoanProductContent\Schemas\LoanProductContentForm;
use App\Filament\Resources\LoanProductContent\Tables\LoanProductContentTable;
use App\Models\LoanProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 8 — a restricted editing boundary onto the SAME LoanProduct record
 * the full LoanProductResource manages, exposing only website marketing
 * content (see LoanProductContentForm). This is deliberately NOT a second
 * LoanProduct model/table — it's a second door onto the same one, guarded
 * by its own permission (ViewAny/View/Update:LoanProductContent) that is
 * completely independent of Update:LoanProduct.
 *
 * Authorization here intentionally bypasses Filament's default
 * Gate::getPolicyFor()-based resolution (which would resolve to
 * LoanProductPolicy and therefore Update:LoanProduct, defeating the whole
 * point) by overriding the can*() methods directly with explicit permission
 * checks — the same pattern already used by AdminActivity and
 * MediaGovernance for permissions that don't map 1:1 onto a model policy.
 * See RestrictsUpdateToAllowedFields for the second half of the boundary:
 * even an authorized Marketing user cannot smuggle a business field through
 * this page, because the save path re-asserts an explicit field allowlist
 * immediately before persisting, independent of what the form renders.
 */
class LoanProductContentResource extends Resource
{
    protected static ?string $model = LoanProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Loan Product Content';

    protected static ?string $modelLabel = 'loan product content';

    protected static ?string $slug = 'loan-product-content';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:LoanProductContent');
    }

    public static function canView(Model $record): bool
    {
        return (bool) auth()->user()?->can('View:LoanProductContent');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('Update:LoanProductContent');
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
        return LoanProductContentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanProductContentTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Read-only History only — never Restorable here. A single audit
            // row can mix a marketing edit with an admin's business-field edit
            // (e.g. one save that changed both marketing_headline and
            // min_amount); restoring it would revert both. See LoanProduct's
            // own exclusion from the restorable set for the same reason.
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoanProductContent::route('/'),
            'edit' => EditLoanProductContent::route('/{record}/edit'),
        ];
    }
}
