<?php

namespace App\Modules\Newsletter\Models;

use Database\Factories\NewsletterTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable campaign body. `content` is the INNER html of an email — the
 * branded shell (logo, footer, unsubscribe link) always comes from the
 * emails.newsletter.layout view, so no template can accidentally ship an email
 * without an unsubscribe link.
 */
#[Fillable(['name', 'description', 'content', 'is_active'])]
class NewsletterTemplate extends Model
{
    /** @use HasFactory<NewsletterTemplateFactory> */
    use HasFactory;

    protected static function newFactory(): NewsletterTemplateFactory
    {
        return NewsletterTemplateFactory::new();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
