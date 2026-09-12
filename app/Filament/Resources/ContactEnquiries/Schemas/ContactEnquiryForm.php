<?php

namespace App\Filament\Resources\ContactEnquiries\Schemas;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactEnquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')->label('Mobile number')->disabled(),
                Select::make('enquiry_type')
                    ->label('Type')
                    ->options(EnquiryType::class)
                    ->disabled(),
                TextInput::make('source')->disabled(),
                /*
                 * The only editable lifecycle field. Closing an enquiry stamps
                 * handled_at automatically (see ContactEnquiry::booted()), so the
                 * two never disagree — the picker below is for correcting the time.
                 */
                Select::make('status')
                    ->options(EnquiryStatus::class)
                    ->required()
                    ->native(false),
                TextInput::make('name')->disabled()->placeholder('Not provided'),
                TextInput::make('email')->disabled()->placeholder('Not provided'),
                Textarea::make('message')->disabled()->columnSpanFull()->rows(4)->placeholder('Not provided'),
                TextInput::make('enquiry_count')
                    ->label('Times enquired')
                    ->disabled()
                    ->helperText('A repeat enquiry from this number bumps this count instead of creating a second record.'),
                DateTimePicker::make('handled_at')
                    ->label('Handled at')
                    ->helperText('Set this once the enquiry has been followed up.'),
            ]);
    }
}
