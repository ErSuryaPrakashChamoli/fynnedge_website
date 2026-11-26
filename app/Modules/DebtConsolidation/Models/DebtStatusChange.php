<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\User;
use App\Modules\DebtConsolidation\Enums\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One status transition of a case, lender application or closure. Rows are
 * only ever inserted.
 */
#[Fillable(['subject_type', 'subject_id', 'from_status', 'to_status', 'note', 'actor_type', 'user_id'])]
class DebtStatusChange extends Model
{
    /**
     * The status history is insert-only: a recorded change is never edited
     * or removed (it goes only with its case, by the database cascade).
     */
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Status history cannot be changed.'));
        static::deleting(fn (): never => throw new LogicException('Status history cannot be deleted.'));
    }

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
