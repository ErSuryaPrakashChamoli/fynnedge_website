<?php

namespace App\Filament\Resources\CreditScoreChecks\Schemas;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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

                // Only for live (Surepass) checks: the reason a check gave no score,
                // which the visitor is never shown. Never the provider's own text.
                Section::make('Surepass result')
                    ->visible(fn (?CreditScoreCheck $record): bool => $record?->provider === 'surepass')
                    ->columns(3)
                    ->components([
                        TextEntry::make('source_bureau')
                            ->label('Score from')
                            ->state(fn (CreditScoreCheck $record): ?string => $record->sourceBureau() !== $record->bureau
                                ? $record->sourceBureau()?->getLabel().' (standing in: no '.$record->bureau->getLabel().' API yet)'
                                : $record->bureau->getLabel())
                            ->columnSpanFull(),
                        TextEntry::make('reason')
                            ->label('Reason')
                            ->state(fn (CreditScoreCheck $record): string => $record->liveFailure()?->getLabel()
                                ?? (filled($record->raw_response['reused_from'] ?? null) ? 'Stored score reused — no Surepass call' : ($record->score !== null ? 'Score received' : 'No score returned'))),
                        TextEntry::make('http_status')
                            ->label('HTTP status')
                            ->state(fn (CreditScoreCheck $record): ?string => isset($record->raw_response['http_status']) ? (string) $record->raw_response['http_status'] : null)
                            ->placeholder('—'),
                        TextEntry::make('message_code')
                            ->label('Surepass message code')
                            ->state(fn (CreditScoreCheck $record): ?string => $record->raw_response['message_code'] ?? null)
                            ->placeholder('—'),
                        TextEntry::make('what_it_means')
                            ->label('What it means / what to do')
                            ->state(fn (CreditScoreCheck $record): ?string => $record->liveFailure()?->safeMessage())
                            ->placeholder('—')
                            ->columnSpanFull(),
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
