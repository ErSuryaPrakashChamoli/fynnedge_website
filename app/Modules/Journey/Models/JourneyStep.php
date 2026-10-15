<?php

namespace App\Modules\Journey\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Modules\Journey\Models\Concerns\EvaluatesCondition;
use Database\Factories\JourneyStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['journey_definition_id', 'key', 'title', 'description', 'order', 'condition_rules', 'title_hi', 'description_hi'])]
class JourneyStep extends Model
{
    /** @use HasFactory<JourneyStepFactory> */
    use EvaluatesCondition, HasFactory, HasPublicId, HasTranslations;

    protected static function newFactory(): JourneyStepFactory
    {
        return JourneyStepFactory::new();
    }

    protected function casts(): array
    {
        return [
            'condition_rules' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function isVisibleGiven(array $responses): bool
    {
        return $this->conditionIsSatisfied($this->condition_rules, $responses);
    }

    public function journeyDefinition(): BelongsTo
    {
        return $this->belongsTo(JourneyDefinition::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(JourneyStepField::class)->orderBy('order');
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'title',
            'description',
        ];
    }
}
