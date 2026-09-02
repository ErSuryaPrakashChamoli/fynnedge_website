<?php

namespace App\Filament\Resources\CreditScoreChecks\Schemas;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreditScoreCheckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Check')
                    ->columns(3)
                    ->components([
                        Select::make('bureau')->options(BureauName::class)->disabled(),
                        Select::make('status')->options(CreditCheckStatus::class)->disabled(),
                        TextInput::make('score')->disabled(),
                        TextInput::make('provider')->disabled(),
                        TextInput::make('ip_address')->label('IP address')->disabled(),
                        DateTimePicker::make('completed_at')->disabled(),
                    ]),

                Section::make('Applicant')
                    ->columns(3)
                    ->components([
                        TextInput::make('full_name')->label('Full name')->disabled(),
                        TextInput::make('mobile_number')->label('Mobile number')->disabled(),
                        TextInput::make('pan_number')->label('PAN')->disabled(),
                        DatePicker::make('date_of_birth')->label('Date of birth')->disabled(),
                        DateTimePicker::make('mobile_verified_at')->label('Mobile verified at')->disabled(),
                        DateTimePicker::make('consent_given_at')->label('Consent given at')->disabled(),
                    ]),
            ]);
    }
}
