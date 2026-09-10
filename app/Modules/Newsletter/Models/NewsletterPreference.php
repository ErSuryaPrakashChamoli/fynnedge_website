<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category', 'is_subscribed'])]
class NewsletterPreference extends Model
{
    protected function casts(): array
    {
        return [
            'category' => NewsletterCategory::class,
            'is_subscribed' => 'boolean',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
