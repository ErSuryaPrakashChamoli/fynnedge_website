<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What makes a promo bar rise into view. Exit intent needs a mouse, so on
 * touch screens it falls back to half-way down the page.
 */
enum PromoBarTrigger: string implements HasLabel
{
    case Scroll = 'scroll';
    case Delay = 'delay';
    case ExitIntent = 'exit_intent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scroll => 'After scrolling down the page',
            self::Delay => 'After a few seconds on the page',
            self::ExitIntent => 'When the visitor heads for the tab bar (exit intent)',
        };
    }

    /**
     * The largest trigger value that still makes sense: a percentage of the
     * page for scroll, seconds for delay. Exit intent takes no value.
     */
    public function maxValue(): int
    {
        return match ($this) {
            self::Scroll => 100,
            self::Delay => 120,
            self::ExitIntent => 0,
        };
    }
}
