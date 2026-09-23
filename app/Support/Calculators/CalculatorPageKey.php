<?php

namespace App\Support\Calculators;

use Filament\Support\Contracts\HasLabel;

/**
 * The investment calculator pages whose "About" content CalculatorPageSeeder
 * ships. Every calculator page (loan ones included) can have a CalculatorPage
 * row — the admin's list comes from CalculatorCatalog::pages().
 */
enum CalculatorPageKey: string implements HasLabel
{
    case FixedDeposit = 'fixed-deposit';
    case Sip = 'sip';
    case DailySip = 'daily-sip';
    case Gst = 'gst';

    public function getLabel(): string
    {
        return match ($this) {
            self::FixedDeposit => 'Fixed Deposit Calculator',
            self::Sip => 'SIP Calculator',
            self::DailySip => 'Daily SIP Calculator',
            self::Gst => 'GST Calculator',
        };
    }
}
