<?php

namespace App\Modules\CreditBureau\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a customer gave consent for a credit bureau pull. Only OnlineJourney is
 * captured by the website itself (the journey's credit-check checkbox); the
 * others are recorded by an employee, who must cite the evidence.
 */
enum ConsentMethod: string implements HasLabel
{
    case OnlineJourney = 'online_journey';
    case Otp = 'otp';
    case WrittenForm = 'written_form';
    case RecordedCall = 'recorded_call';
    case Email = 'email';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnlineJourney => 'Website application (consent checkbox)',
            self::Otp => 'OTP confirmed by the customer',
            self::WrittenForm => 'Signed consent form',
            self::RecordedCall => 'Recorded phone call',
            self::Email => 'Email from the customer',
        };
    }

    /**
     * Methods an employee may record by hand.
     *
     * @return array<string, string>
     */
    public static function employeeRecordable(): array
    {
        return collect(self::cases())
            ->reject(fn (self $method) => $method === self::OnlineJourney)
            ->mapWithKeys(fn (self $method) => [$method->value => $method->getLabel()])
            ->all();
    }
}
