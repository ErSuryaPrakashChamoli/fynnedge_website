<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use Database\Factories\DebtCalculationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An immutable record of one calculation: the exact inputs, the results, the
 * formula version and the scoring configuration version. Reports are built
 * from snapshots only, so a report generated today can be regenerated
 * identically later. Rows are never updated.
 */
#[Fillable([
    'debt_application_id', 'kind', 'formula_version', 'scoring_config_id', 'scoring_version', 'inputs', 'inputs_hash', 'results',
    'total_outstanding', 'net_benefit', 'suitability_score', 'suitability_band', 'created_by_type', 'created_by',
])]
class DebtCalculationSnapshot extends Model
{
    /** @use HasFactory<DebtCalculationSnapshotFactory> */
    use HasFactory, HasPublicId;

    public const UPDATED_AT = null;

    public const KIND_ASSESSMENT = 'assessment';

    public const KIND_CALCULATOR = 'calculator';

    protected static function newFactory(): DebtCalculationSnapshotFactory
    {
        return DebtCalculationSnapshotFactory::new();
    }

    protected static function booted(): void
    {
        // Nothing about a stored calculation changes. The one write allowed is
        // linking a visitor's calculator snapshot to the case they then opened.
        static::updating(function (self $snapshot): void {
            $changed = array_diff(array_keys($snapshot->getDirty()), ['debt_application_id', 'updated_at']);

            if ($changed !== [] || ($snapshot->isDirty('debt_application_id') && $snapshot->getOriginal('debt_application_id') !== null)) {
                throw new LogicException('Calculation snapshots are immutable.');
            }
        });

        // Only the retention rule removes snapshots: anonymous calculator ones.
        static::deleting(function (self $snapshot): void {
            if ($snapshot->kind !== self::KIND_CALCULATOR || $snapshot->debt_application_id !== null) {
                throw new LogicException('Only an anonymous calculator snapshot can be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'results' => 'array',
            'total_outstanding' => 'decimal:2',
            'net_benefit' => 'decimal:2',
            'suitability_score' => 'decimal:2',
            'suitability_band' => SuitabilityBand::class,
            'created_by_type' => ActorType::class,
            'created_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function scoringConfig(): BelongsTo
    {
        return $this->belongsTo(DebtScoringConfig::class, 'scoring_config_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function caseInput(): CaseInput
    {
        return CaseInput::fromArray($this->inputs);
    }

    /**
     * Results for one scenario (default: the recommended one, else the first).
     *
     * @return array<string, mixed>|null
     */
    public function scenario(?string $key = null): ?array
    {
        $key ??= $this->results['recommended_scenario_key'] ?? null;
        $scenarios = $this->results['scenarios'] ?? [];

        foreach ($scenarios as $scenario) {
            if ($key === null || ($scenario['scenario']['key'] ?? null) === $key) {
                return $scenario;
            }
        }

        return $scenarios[0] ?? null;
    }

    /**
     * Whether the stored inputs still hash to the stored hash (tamper check).
     */
    public function inputsIntact(): bool
    {
        return hash_equals($this->inputs_hash, $this->caseInput()->hash());
    }
}
