<?php

namespace App\Filament\Resources\Employers;

use App\Filament\Resources\Employers\Pages\CreateEmployer;
use App\Filament\Resources\Employers\Pages\EditEmployer;
use App\Filament\Resources\Employers\Pages\ListEmployers;
use App\Filament\Resources\Employers\Schemas\EmployerForm;
use App\Filament\Resources\Employers\Tables\EmployersTable;
use App\Modules\Eligibility\Models\Employer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployerResource extends Resource
{
    protected static ?string $model = Employer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return EmployerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployersTable::configure($table);
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
            'index' => ListEmployers::route('/'),
            'create' => CreateEmployer::route('/create'),
            'edit' => EditEmployer::route('/{record}/edit'),
        ];
    }
}
