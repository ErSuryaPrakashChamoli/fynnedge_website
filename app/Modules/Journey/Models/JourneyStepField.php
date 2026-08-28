<?php

namespace App\Modules\Journey\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Models\Concerns\EvaluatesCondition;
use Database\Factories\JourneyStepFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journey_step_id', 'key', 'label', 'type', 'options', 'validation_rules', 'help_text', 'order', 'conditional_on'])]
class JourneyStepField extends Model
{
    /** @use HasFactory<JourneyStepFieldFactory> */
    use EvaluatesCondition, HasFactory, HasPublicId;

    protected static function newFactory(): JourneyStepFieldFactory
    {
        return JourneyStepFieldFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'options' => 'array',
            'validation_rules' => 'array',
            'conditional_on' => 'array',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    /**
     * @param  array<string, mixed>  $responses  field_key => value, for fields already answered
     */
    public function isVisibleGiven(array $responses): bool
    {
        return $this->conditionIsSatisfied($this->conditional_on, $responses);
    }
}
