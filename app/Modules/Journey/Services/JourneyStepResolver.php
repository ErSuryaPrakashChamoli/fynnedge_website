<?php

namespace App\Modules\Journey\Services;

use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use Illuminate\Support\Collection;

class JourneyStepResolver
{
    /**
     * @param  array<string, mixed>  $responses
     * @return Collection<int, JourneyStep>
     */
    public function visibleSteps(JourneyDefinition $definition, array $responses): Collection
    {
        return $definition->steps->filter(fn (JourneyStep $step) => $step->isVisibleGiven($responses))->values();
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function visibleFields(JourneyStep $step, array $responses): Collection
    {
        return $step->fields->filter(fn ($field) => $field->isVisibleGiven($responses))->values();
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function nextStep(JourneyDefinition $definition, JourneyStep $current, array $responses): ?JourneyStep
    {
        $visible = $this->visibleSteps($definition, $responses);
        $index = $visible->search(fn (JourneyStep $step) => $step->id === $current->id);

        if ($index === false) {
            return null;
        }

        return $visible->get($index + 1);
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function previousStep(JourneyDefinition $definition, JourneyStep $current, array $responses): ?JourneyStep
    {
        $visible = $this->visibleSteps($definition, $responses);
        $index = $visible->search(fn (JourneyStep $step) => $step->id === $current->id);

        if ($index === false || $index === 0) {
            return null;
        }

        return $visible->get($index - 1);
    }

    public function firstStep(JourneyDefinition $definition): ?JourneyStep
    {
        return $this->visibleSteps($definition, [])->first();
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<string, array<int, string>>
     */
    public function validationRulesFor(JourneyStep $step, array $responses): array
    {
        return $this->visibleFields($step, $responses)
            ->mapWithKeys(fn ($field) => [$field->key => $field->validation_rules ?? []])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array{completed: int, total: int, percent: int}
     */
    public function progress(JourneyDefinition $definition, JourneyStep $current, array $responses): array
    {
        $visible = $this->visibleSteps($definition, $responses);
        $total = $visible->count();
        $index = $visible->search(fn (JourneyStep $step) => $step->id === $current->id);
        $completed = $index === false ? 0 : $index;

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }
}
