<?php

namespace App\Filament\Resources\HowItWorksSteps;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\HowItWorksSteps\Pages\CreateHowItWorksStep;
use App\Filament\Resources\HowItWorksSteps\Pages\EditHowItWorksStep;
use App\Filament\Resources\HowItWorksSteps\Pages\ListHowItWorksSteps;
use App\Filament\Resources\HowItWorksSteps\Schemas\HowItWorksStepForm;
use App\Filament\Resources\HowItWorksSteps\Tables\HowItWorksStepsTable;
use App\Models\HowItWorksStep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HowItWorksStepResource extends Resource
{
    protected static ?string $model = HowItWorksStep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'How It Works Steps';

    public static function form(Schema $schema): Schema
    {
        return HowItWorksStepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HowItWorksStepsTable::configure($table);
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
            'index' => ListHowItWorksSteps::route('/'),
            'create' => CreateHowItWorksStep::route('/create'),
            'edit' => EditHowItWorksStep::route('/{record}/edit'),
        ];
    }
}
