<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed headline statistic on the homepage — "Cities served: 550+",
 * "Partner banks & NBFCs: 40+".
 *
 * `value` is a string, not a number, so an admin can publish "2.16", "4.5" or
 * "20" without the model second-guessing the format; `prefix`/`suffix` carry
 * the "₹" / "+" / "Cr" decoration separately so the number itself stays clean.
 *
 * Deliberately NOT seeded with any figure: the homepage falls back to the two
 * counts it can derive from real records (published loan products, active
 * lenders) until an admin publishes rows here, so an empty table can never
 * put an unverified claim on the page.
 *
 * Follows HowItWorksStep's shape — draft/publish toggle and sort_order, no
 * scheduling — since a handful of headline figures don't need to be timed.
 *
 * Text only, by design: the homepage strip has no markup for an icon, so the
 * admin form offers none. Any number of rows can be published and the strip
 * spreads whatever exists evenly across the row.
 */
#[Fillable(['label', 'value', 'prefix', 'suffix', 'sort_order', 'status'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    /**
     * The figure exactly as it should read on the page, e.g. "₹20Cr+".
     */
    public function displayValue(): string
    {
        return $this->prefix.$this->value.$this->suffix;
    }
}
