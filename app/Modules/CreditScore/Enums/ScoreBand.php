<?php

namespace App\Modules\CreditScore\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a 300–900 score sits, as Indian lenders broadly read it — the same
 * cut-offs CIBIL, Experian, Equifax and CRIF use in their consumer guidance
 * (750+ is what most lenders look for before offering their best rates). It
 * drives the result meter's colours, the band label and which advice the
 * result page leads with.
 */
enum ScoreBand: string implements HasLabel
{
    case Poor = 'poor';
    case Average = 'average';
    case Good = 'good';
    case Excellent = 'excellent';

    public const LOWEST_SCORE = 300;

    public const HIGHEST_SCORE = 900;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= self::Excellent->min() => self::Excellent,
            $score >= self::Good->min() => self::Good,
            $score >= self::Average->min() => self::Average,
            default => self::Poor,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Poor => __('Poor'),
            self::Average => __('Average'),
            self::Good => __('Good'),
            self::Excellent => __('Excellent'),
        };
    }

    public function min(): int
    {
        return match ($this) {
            self::Poor => self::LOWEST_SCORE,
            self::Average => 550,
            self::Good => 650,
            self::Excellent => 750,
        };
    }

    public function max(): int
    {
        $next = $this->next();

        return $next === null ? self::HIGHEST_SCORE : $next->min() - 1;
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Poor => self::Average,
            self::Average => self::Good,
            self::Good => self::Excellent,
            self::Excellent => null,
        };
    }

    /** What the band means to a lender, in one line. */
    public function verdict(): string
    {
        return match ($this) {
            self::Poor => __('Lenders see a high risk at this level — approvals are difficult and rates are high until it improves.'),
            self::Average => __('You can get credit, but usually at higher rates and with stricter checks.'),
            self::Good => __('Most lenders will approve you — a little more and you unlock the best rates.'),
            self::Excellent => __('Lenders see you as a low-risk borrower — you qualify for the best rates and fastest approvals.'),
        };
    }
}
