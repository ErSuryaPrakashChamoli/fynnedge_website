<?php

namespace App\Filament\Resources\DocumentRequirements;

use App\Filament\Resources\DocumentRequirements\Pages\CreateDocumentRequirement;
use App\Filament\Resources\DocumentRequirements\Pages\EditDocumentRequirement;
use App\Filament\Resources\DocumentRequirements\Pages\ListDocumentRequirements;
use App\Filament\Resources\DocumentRequirements\Schemas\DocumentRequirementForm;
use App\Filament\Resources\DocumentRequirements\Tables\DocumentRequirementsTable;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentRequirementResource extends Resource
{
    protected static ?string $model = LenderProductDocumentRequirement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Document Requirements';

    public static function form(Schema $schema): Schema
    {
        return DocumentRequirementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentRequirementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentRequirements::route('/'),
            'create' => CreateDocumentRequirement::route('/create'),
            'edit' => EditDocumentRequirement::route('/{record}/edit'),
        ];
    }
}
