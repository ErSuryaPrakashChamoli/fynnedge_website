<?php

namespace App\Filament\Resources\SchemaTemplates;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\SchemaTemplates\Pages\CreateSchemaTemplate;
use App\Filament\Resources\SchemaTemplates\Pages\EditSchemaTemplate;
use App\Filament\Resources\SchemaTemplates\Pages\ListSchemaTemplates;
use App\Filament\Resources\SchemaTemplates\Schemas\SchemaTemplateForm;
use App\Filament\Resources\SchemaTemplates\Tables\SchemaTemplatesTable;
use App\Models\SchemaTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SchemaTemplateResource extends Resource
{
    protected static ?string $model = SchemaTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Schema Templates';

    protected static ?string $modelLabel = 'schema template';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SchemaTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchemaTemplatesTable::configure($table);
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
            'index' => ListSchemaTemplates::route('/'),
            'create' => CreateSchemaTemplate::route('/create'),
            'edit' => EditSchemaTemplate::route('/{record}/edit'),
        ];
    }
}
