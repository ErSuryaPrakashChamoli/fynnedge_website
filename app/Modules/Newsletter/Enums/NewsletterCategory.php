<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Topic opt-ins offered on the preferences page and usable as segment criteria.
 *
 * Adding a topic is a new case here plus nothing else — preferences store the
 * value as a string, so no migration is involved. A subscriber with no stored
 * preference rows counts as subscribed to every category, which is what someone
 * who used the single-field signup form asked for.
 */
enum NewsletterCategory: string implements HasLabel
{
    case PersonalFinance = 'personal_finance';
    case CreditAndCibil = 'credit_and_cibil';
    case Loans = 'loans';

    public function getLabel(): string
    {
        return match ($this) {
            self::PersonalFinance => 'Personal finance',
            self::CreditAndCibil => 'Credit & CIBIL',
            self::Loans => 'Loans',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PersonalFinance => 'Budgeting, saving and everyday money guidance.',
            self::CreditAndCibil => 'Credit scores, credit reports and how to improve them.',
            self::Loans => 'Loan products, eligibility and borrowing tips.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
    }
}
