<?php

namespace App\Filament\Resources\CompanyPhotos;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\CompanyPhotos\Pages\CreateCompanyPhoto;
use App\Filament\Resources\CompanyPhotos\Pages\EditCompanyPhoto;
use App\Filament\Resources\CompanyPhotos\Pages\ListCompanyPhotos;
use App\Filament\Resources\CompanyPhotos\Schemas\CompanyPhotoForm;
use App\Filament\Resources\CompanyPhotos\Tables\CompanyPhotosTable;
use App\Models\CompanyPhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompanyPhotoResource extends Resource
{
    protected static ?string $model = CompanyPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Life at FynnEdge Photos';

    protected static ?string $modelLabel = 'photo';

    public static function form(Schema $schema): Schema
    {
        return CompanyPhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyPhotosTable::configure($table);
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
            'index' => ListCompanyPhotos::route('/'),
            'create' => CreateCompanyPhoto::route('/create'),
            'edit' => EditCompanyPhoto::route('/{record}/edit'),
        ];
    }
}
