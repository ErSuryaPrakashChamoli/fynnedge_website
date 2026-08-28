<?php

namespace App\Filament\Resources\ContactEnquiries\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactEnquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->disabled(),
                TextInput::make('email')->disabled(),
                TextInput::make('phone')->disabled(),
                Textarea::make('message')->disabled()->columnSpanFull()->rows(4),
                DateTimePicker::make('handled_at')
                    ->label('Handled at')
                    ->helperText('Set this once the enquiry has been followed up.'),
            ]);
    }
}
