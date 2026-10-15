<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Modules\MobileOtp\Services\MobileNumber;
use Closure;
use Filament\Forms\Components\TextInput;
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
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'A customer with this email address already exists. Search for them in the Customers list instead.']),
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->tel()
                    ->maxLength(15)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! MobileNumber::isValid(MobileNumber::normalize($value))) {
                            $fail('Enter a 10-digit Indian mobile number.');
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? MobileNumber::normalize($state) : null),
            ]);
    }
}
