<?php

namespace App\Modules\Eligibility\Services;

use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Models\EligibilityRuleCondition;

class RuleConditionEvaluator
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function evaluate(EligibilityRuleCondition $condition, array $attributes): bool
    {
        $actual = $attributes[$condition->attribute] ?? null;
        $expected = $condition->value;

        return match ($condition->operator) {
            RuleOperator::Equals => $this->looseEquals($actual, $expected),
            RuleOperator::NotEquals => ! $this->looseEquals($actual, $expected),
            RuleOperator::GreaterThan => $this->compareNumeric($actual, $expected, fn ($a, $b) => $a > $b),
            RuleOperator::GreaterThanOrEqual => $this->compareNumeric($actual, $expected, fn ($a, $b) => $a >= $b),
            RuleOperator::LessThan => $this->compareNumeric($actual, $expected, fn ($a, $b) => $a < $b),
            RuleOperator::LessThanOrEqual => $this->compareNumeric($actual, $expected, fn ($a, $b) => $a <= $b),
            RuleOperator::In => $this->inList($actual, (array) $expected),
            RuleOperator::NotIn => ! $this->inList($actual, (array) $expected),
            RuleOperator::Between => $this->between($actual, array_values((array) $expected)),
            RuleOperator::Contains => is_string($actual) && is_string($expected)
                && str_contains(strtolower($actual), strtolower($expected)),
            RuleOperator::StartsWith => is_string($actual) && is_string($expected)
                && str_starts_with(strtolower($actual), strtolower($expected)),
        };
    }

    /**
     * Attribute values come from three different sources (typed DTO fields, raw HTML form
     * strings like "yes"/"no", and admin-entered rule values) so equality is intentionally
     * forgiving: booleans, boolean-ish words, and numbers all compare on their canonical form.
     */
    private function canonical(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return is_bool($value) ? $value : (float) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', 'no', 'off', ''], true)) {
                return false;
            }

            if (is_numeric($normalized)) {
                return (float) $normalized;
            }

            return $normalized;
        }

        return $value;
    }

    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        return $this->canonical($actual) === $this->canonical($expected);
    }

    private function compareNumeric(mixed $actual, mixed $expected, callable $comparator): bool
    {
        if (! is_numeric($actual) || ! is_numeric($expected)) {
            return false;
        }

        return $comparator((float) $actual, (float) $expected);
    }

    /**
     * @param  array<int, mixed>  $options
     */
    private function inList(mixed $actual, array $options): bool
    {
        $needle = $this->canonical($actual);

        foreach ($options as $option) {
            if ($this->canonical($option) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $bounds
     */
    private function between(mixed $actual, array $bounds): bool
    {
        if (! is_numeric($actual) || count($bounds) < 2 || ! is_numeric($bounds[0]) || ! is_numeric($bounds[1])) {
            return false;
        }

        [$min, $max] = [min((float) $bounds[0], (float) $bounds[1]), max((float) $bounds[0], (float) $bounds[1])];

        return (float) $actual >= $min && (float) $actual <= $max;
    }
}
