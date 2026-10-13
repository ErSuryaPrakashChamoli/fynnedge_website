<?php

namespace App\Filament\Resources\MobileOtpChallenges\Schemas;

use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Everything safe to know about one challenge. Never the code or its hash
 * (otp_hash is also $hidden on the model), never the full number, never the
 * provider's raw response — only the normalised fields RequestMobileOtp stored.
 */
class MobileOtpChallengeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verification')
                    ->description('Only a visitor entering the correct code makes a challenge Verified.')
                    ->columns(3)
                    ->components([
                        TextEntry::make('mobile_number')
                            ->label('Mobile number')
                            ->formatStateUsing(fn (MobileOtpChallenge $record): string => '+91 '.$record->maskedMobileNumber()),
                        TextEntry::make('purpose')
                            ->badge()
                            ->placeholder('Not recorded (before SMS integration)'),
                        TextEntry::make('effective_status')
                            ->label('Status')
                            ->state(fn (MobileOtpChallenge $record) => $record->effectiveStatus())
                            ->badge(),
                        TextEntry::make('attempts')
                            ->label('Verification attempts')
                            ->state(fn (MobileOtpChallenge $record): string => "{$record->attempts} of {$record->max_attempts}"),
                        TextEntry::make('created_at')->label('OTP requested at')->dateTime('j M Y, H:i:s'),
                        TextEntry::make('expires_at')->label('Expires at')->dateTime('j M Y, H:i:s'),
                        TextEntry::make('verified_at')->label('Verified at')->dateTime('j M Y, H:i:s')->placeholder('Not verified'),
                        TextEntry::make('consumed_at')
                            ->label('Used by form at')
                            ->dateTime('j M Y, H:i:s')
                            ->placeholder('Not used')
                            ->helperText('When the enquiry, application step or credit check it protected was submitted.'),
                        TextEntry::make('updated_at')->label('Last updated')->dateTime('j M Y, H:i:s'),
                    ]),

                Section::make('SMS request')
                    ->description('"Accepted by provider" means the gateway took the request — not that the handset received it.')
                    ->columns(3)
                    ->components([
                        TextEntry::make('sms_provider')->label('Provider')->placeholder('—'),
                        TextEntry::make('sms_status')->label('SMS status')->badge(),
                        TextEntry::make('sms_sent_at')->label('Accepted at')->dateTime('j M Y, H:i:s')->placeholder('—'),
                        TextEntry::make('sms_provider_reference')->label('Provider reference')->placeholder('—')->copyable(),
                        TextEntry::make('sms_failure_reason')->label('Failure category')->placeholder('—'),
                        TextEntry::make('sms_http_status')->label('Provider HTTP status')->placeholder('—'),
                    ]),

                Section::make('Request')
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        TextEntry::make('public_id')->label('Challenge ID')->copyable(),
                        TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                    ]),
            ]);
    }
}
