<?php

namespace App\Modules\Newsletter\Models;

use App\Models\Article;
use App\Models\User;
use App\Modules\Newsletter\Enums\CampaignStatus;
use Database\Factories\NewsletterCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One newsletter send.
 *
 * `article_id` records which blog post the body was generated from, but the
 * body itself is COPIED into `content` when generated and stays editable —
 * editing the article later must never rewrite an email that already went out.
 * Nothing sends automatically when an article is published; a campaign is
 * always an explicit human action.
 */
#[Fillable([
    'name', 'subject', 'preview_text', 'content', 'status', 'newsletter_segment_id',
    'newsletter_template_id', 'article_id', 'cta_label', 'cta_url', 'scheduled_at', 'created_by',
])]
class NewsletterCampaign extends Model
{
    /** @use HasFactory<NewsletterCampaignFactory> */
    use HasFactory;

    protected static function newFactory(): NewsletterCampaignFactory
    {
        return NewsletterCampaignFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(NewsletterSegment::class, 'newsletter_segment_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NewsletterTemplate::class, 'newsletter_template_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class);
    }

    public function scopeDueForSending(Builder $query): void
    {
        $query->where('status', CampaignStatus::Scheduled)->where('scheduled_at', '<=', now());
    }

    /**
     * The audience this campaign would send to right now: its segment, or every
     * mailable subscriber when it has none. Always re-evaluated at send time,
     * never at compose time, so someone who unsubscribes in between is dropped.
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function audience(): Builder
    {
        return $this->segment?->subscribers() ?? NewsletterSubscriber::query()->mailable();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
