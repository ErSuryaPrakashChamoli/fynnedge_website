<?php

namespace App\Filament\Resources\JourneySessions\Schemas;

use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneySession;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JourneySessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application')
                    ->columns(2)
                    ->components([
                        Select::make('loan_product_id')
                            ->relationship('loanProduct', 'name')
                            ->disabled(),
                        Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->disabled(),
                        Select::make('status')
                            ->options(JourneySessionStatus::class)
                            ->disabled(),
                        Select::make('current_step_id')
                            ->relationship('currentStep', 'title')
                            ->disabled(),
                        DateTimePicker::make('completed_at')
                            ->disabled(),
                    ]),

                Section::make('Credit bureau consent')
                    ->relationship('creditConsent')
                    ->visible(fn (?JourneySession $record) => $record?->creditConsent !== null)
                    ->columns(3)
                    ->components([
                        TextInput::make('purpose')->disabled()->columnSpanFull(),
                        TextInput::make('terms_version')->label('Terms version')->disabled(),
                        TextInput::make('ip_address')->label('IP address')->disabled(),
                        DateTimePicker::make('consented_at')->disabled(),
                    ]),

                Section::make('Attribution')
                    ->columns(3)
                    ->collapsed()
                    ->components([
                        TextInput::make('utm_source')->disabled(),
                        TextInput::make('utm_medium')->disabled(),
                        TextInput::make('utm_campaign')->disabled(),
                        TextInput::make('referrer')->disabled()->columnSpanFull(),
                        TextInput::make('landing_page')->disabled()->columnSpanFull(),
                    ]),
            ]);
    }
}
