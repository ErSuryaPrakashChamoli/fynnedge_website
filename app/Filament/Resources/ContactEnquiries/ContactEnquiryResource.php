<?php

namespace App\Filament\Resources\ContactEnquiries;

use App\Filament\Resources\ContactEnquiries\Pages\EditContactEnquiry;
use App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries;
use App\Filament\Resources\ContactEnquiries\Schemas\ContactEnquiryForm;
use App\Filament\Resources\ContactEnquiries\Tables\ContactEnquiriesTable;
use App\Models\ContactEnquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactEnquiryResource extends Resource
{
    protected static ?string $model = ContactEnquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ContactEnquiryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactEnquiriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactEnquiries::route('/'),
            'edit' => EditContactEnquiry::route('/{record}/edit'),
        ];
    }
}
