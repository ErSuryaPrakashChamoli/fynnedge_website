<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['question', 'answer', 'sort_order', 'status', 'faqable_type', 'faqable_id', 'published_at', 'expires_at'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
