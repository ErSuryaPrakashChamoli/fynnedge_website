<?php

namespace App\Filament\Resources\EligibilityRuleSets;

use App\Filament\Resources\EligibilityRuleSets\Pages\CreateEligibilityRuleSet;
use App\Filament\Resources\EligibilityRuleSets\Pages\EditEligibilityRuleSet;
use App\Filament\Resources\EligibilityRuleSets\Pages\ListEligibilityRuleSets;
use App\Filament\Resources\EligibilityRuleSets\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\EligibilityRuleSets\RelationManagers\RulesRelationManager;
use App\Filament\Resources\EligibilityRuleSets\Schemas\EligibilityRuleSetForm;
use App\Filament\Resources\EligibilityRuleSets\Tables\EligibilityRuleSetsTable;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EligibilityRuleSetResource extends Resource
{
    protected static ?string $model = EligibilityRuleSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Eligibility Rules';

    public static function form(Schema $schema): Schema
    {
        return EligibilityRuleSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EligibilityRuleSetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RulesRelationManager::class,
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEligibilityRuleSets::route('/'),
            'create' => CreateEligibilityRuleSet::route('/create'),
            'edit' => EditEligibilityRuleSet::route('/{record}/edit'),
        ];
    }
}
