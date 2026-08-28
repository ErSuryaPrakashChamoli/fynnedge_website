<?php

namespace App\Modules\Journey\Models\Concerns;

trait EvaluatesCondition
{
    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $responses
     */
    protected function conditionIsSatisfied(?array $condition, array $responses): bool
    {
        if (! $condition) {
            return true;
        }

        $actual = $responses[$condition['field']] ?? null;
        $expected = $condition['value'] ?? null;

        return match ($condition['operator'] ?? '=') {
            '!=' => $actual != $expected,
            'in' => in_array($actual, (array) $expected),
            default => $actual == $expected,
        };
    }
}
