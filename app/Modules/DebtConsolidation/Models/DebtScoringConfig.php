<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use Database\Factories\DebtScoringConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A versioned suitability scoring configuration for one applicant type. Only
 * drafts may be edited; activating one retires the previous active version.
 * Snapshots record which version scored them.
 */
#[Fillable(['applicant_type', 'version', 'status', 'weights', 'thresholds', 'notes', 'created_by', 'activated_by', 'activated_at', 'retired_at'])]
class DebtScoringConfig extends Model
{
    /** @use HasFactory<DebtScoringConfigFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): DebtScoringConfigFactory
    {
        return DebtScoringConfigFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $config): void {
            if ($config->getOriginal('status') !== LenderPolicyStatus::Draft && $config->isDirty(['weights', 'thresholds', 'applicant_type', 'version'])) {
                throw new LogicException('Only a draft scoring configuration can be changed. Create a new version instead.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'applicant_type' => ApplicantType::class,
            'status' => LenderPolicyStatus::class,
            'weights' => 'array',
            'thresholds' => 'array',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public static function activeFor(ApplicantType $applicantType): ?self
    {
        return self::query()
            ->where('applicant_type', $applicantType)
            ->where('status', LenderPolicyStatus::Active)
            ->latest('activated_at')
            ->latest('id')
            ->first();
    }

    public function toScoringConfig(): ScoringConfig
    {
        return new ScoringConfig($this->applicant_type, $this->version, $this->weights, $this->thresholds ?? []);
    }
}
