<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Modules\CreditScore\Services\CreditReportRegenerationLimit;
use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Services\MobileNumber;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Required when a customer is added by hand; website applications
                // may have created a customer before their name was collected.
                TextInput::make('full_name')
                    ->label('Full name (as on PAN card)')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                // Customers are identified by email (a later website application with
                // the same email joins this record), so a second record is refused.
                // A customer-portal account opened from the credit-score check has a
                // verified mobile but may have no email yet, so editing never forces one.
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'A customer with this email address already exists. Search for them in the Customers list instead.']),
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->helperText('Contact number. The customer-portal login is the verified mobile below, which only an OTP can set.')
                    ->tel()
                    ->maxLength(15)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! MobileNumber::isValid(MobileNumber::normalize($value))) {
                            $fail('Enter a 10-digit Indian mobile number.');
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? MobileNumber::normalize($state) : null),

                // Read-only: entries have no form state, so nothing here can be
                // changed by saving the form. Status changes are header actions.
                Section::make('Customer portal account')
                    ->description('FynnOn customer portal (/my-profile). Profile details are customer-provided unless identity is verified; credit-bureau data never changes them.')
                    ->hiddenOn('create')
                    ->columnSpanFull()
                    ->columns(3)
                    ->components([
                        TextEntry::make('portal_status')
                            ->label('Account status')
                            ->badge()
                            ->state(fn (Customer $record): string => self::portalStatus($record))
                            ->color(fn (string $state): string => match ($state) {
                                'Active' => 'success',
                                'Disabled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('verified_mobile')
                            ->label('Verified mobile (login)')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? MobileNumber::mask($state) : '—')
                            ->placeholder('Not verified'),
                        TextEntry::make('last_login_at')->label('Last sign-in')->dateTime('d M Y, H:i')->placeholder('Never'),
                        TextEntry::make('portal_activated_at')->label('Activated')->dateTime('d M Y, H:i')->placeholder('—'),
                        TextEntry::make('identity_verified_at')->label('Identity verified')->dateTime('d M Y, H:i')->placeholder('Not verified'),
                        TextEntry::make('profile_completion')
                            ->label('Profile completion')
                            ->state(fn (Customer $record): string => $record->profileCompletion().'%'),
                        TextEntry::make('pan_masked')->label('PAN (customer-provided)')->placeholder('—'),
                        TextEntry::make('date_of_birth')->label('Date of birth')->date('d M Y')->placeholder('—'),
                        TextEntry::make('gender')->placeholder('—'),
                        TextEntry::make('next_new_report')
                            ->label('Next new credit report')
                            ->helperText('One new bureau report every '.CreditReportRegenerationLimit::everyMonths().' months, at most '.CreditReportRegenerationLimit::maxPerYear().' a year, per customer and PAN.')
                            ->state(fn (Customer $record): string => app(CreditReportRegenerationLimit::class)->nextNewReportAt($record)?->format('d M Y') ?? 'Available now'),
                        TextEntry::make('address')
                            ->label('Address')
                            ->state(fn (Customer $record): ?string => collect([$record->address_line1, $record->address_line2, $record->city, $record->state, $record->pincode])->filter()->implode(', ') ?: null)
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function portalStatus(Customer $customer): string
    {
        return match (true) {
            $customer->portal_disabled_at !== null => 'Disabled',
            $customer->hasPortalAccess() => 'Active',
            default => 'Not activated',
        };
    }
}
