<?php

namespace App\Modules\DebtConsolidation\Models\Concerns;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtStatusChange;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Status history for cases, lender applications and closures. Each change is
 * written by the action that makes it, with who did it and why.
 */
trait HasStatusHistory
{
    public function statusChanges(): MorphMany
    {
        return $this->morphMany(DebtStatusChange::class, 'subject')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function recordStatusChange(?string $from, string $to, ActorType $actor, ?int $userId = null, ?string $note = null): DebtStatusChange
    {
        return $this->statusChanges()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }
}
