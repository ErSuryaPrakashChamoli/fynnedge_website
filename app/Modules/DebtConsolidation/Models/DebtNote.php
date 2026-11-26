<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\NoteType;
use Database\Factories\DebtNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Communication log, internal remark or follow-up on a case. Internal unless
 * staff mark it visible to the customer.
 */
#[Fillable(['debt_application_id', 'user_id', 'type', 'direction', 'subject', 'body', 'follow_up_at', 'completed_at', 'visible_to_customer'])]
class DebtNote extends Model
{
    /** @use HasFactory<DebtNoteFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): DebtNoteFactory
    {
        return DebtNoteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'follow_up_at' => 'datetime',
            'completed_at' => 'datetime',
            'visible_to_customer' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Follow-ups due by the end of today and not yet done.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueFollowUps(Builder $query): void
    {
        $query->whereNotNull('follow_up_at')->whereNull('completed_at')->where('follow_up_at', '<=', now()->endOfDay());
    }
}
