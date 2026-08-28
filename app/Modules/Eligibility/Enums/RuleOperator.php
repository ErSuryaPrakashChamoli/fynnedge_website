<?php

namespace App\Modules\Eligibility\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuleOperator: string implements HasLabel
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case In = 'in';
    case NotIn = 'not_in';
    case Between = 'between';
    case Contains = 'contains';
    case StartsWith = 'starts_with';

    public function getLabel(): string
    {
        return match ($this) {
            self::Equals => 'Equals (=)',
            self::NotEquals => 'Not equals (!=)',
            self::GreaterThan => 'Greater than (>)',
            self::GreaterThanOrEqual => 'Greater than or equal (>=)',
            self::LessThan => 'Less than (<)',
            self::LessThanOrEqual => 'Less than or equal (<=)',
            self::In => 'Is one of (IN)',
            self::NotIn => 'Is not one of (NOT IN)',
            self::Between => 'Between (inclusive)',
            self::Contains => 'Contains text',
            self::StartsWith => 'Starts with text',
        };
    }

    /**
     * Whether this operator's value is a list rather than a single scalar.
     */
    public function expectsMultipleValues(): bool
    {
        return in_array($this, [self::In, self::NotIn, self::Between]);
    }
}
