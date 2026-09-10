<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A mailing-list subscriber, and nothing else.
 *
 * This entity is intentionally isolated: it has no relation to Customer,
 * JourneySession or Application, and carries no external CRM identifier.
 * Someone who wants monthly finance tips has not applied for anything, and
 * wiring the two together would turn a mailing list into a lead database.
 *
 * Tokens are stored HASHED. The plaintext exists only in the moment it is
 * generated and mailed, so a leaked database backup cannot be used to confirm
 * or unsubscribe anyone, and lookups go through hash equality rather than a
 * string comparison on a secret.
 */
#[Fillable(['name', 'email', 'status', 'source', 'source_url'])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    /**
     * Long enough that a pending signup survives a weekend inbox, short enough
     * that a stale link in a forwarded email stops working.
     */
    public const CONFIRMATION_TTL_DAYS = 7;

    protected static function newFactory(): NewsletterSubscriberFactory
    {
        return NewsletterSubscriberFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    /**
     * @var array<int, string>
     */
    protected $hidden = ['confirmation_token', 'unsubscribe_token'];

    public function preferences(): HasMany
    {
        return $this->hasMany(NewsletterPreference::class);
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class);
    }

    /**
     * The ONLY definition of "may receive a campaign". Every send path queries
     * through this, so there is one place to audit and no way for a new caller
     * to invent a looser rule.
     */
    public function scopeMailable(Builder $query): void
    {
        $query->where('status', SubscriberStatus::Active);
    }

    public function isMailable(): bool
    {
        return $this->status === SubscriberStatus::Active;
    }

    /**
     * A subscriber with no stored preferences receives everything — that is
     * what the single-field signup form asked for. Only once they have visited
     * the preferences page do their choices start narrowing delivery.
     */
    public function acceptsCategory(NewsletterCategory $category): bool
    {
        $preferences = $this->relationLoaded('preferences') ? $this->preferences : $this->preferences()->get();

        if ($preferences->isEmpty()) {
            return true;
        }

        return (bool) $preferences->firstWhere('category', $category->value)?->is_subscribed;
    }

    /**
     * Issues a fresh single-use token, storing only its hash and returning the
     * plaintext for the caller to put in a URL.
     */
    public function issueToken(string $column): string
    {
        $plain = Str::random(48);

        $this->forceFill([
            $column => hash('sha256', $plain),
            ...($column === 'confirmation_token' ? ['confirmation_sent_at' => now()] : []),
        ])->save();

        return $plain;
    }

    /**
     * @return Builder<static>
     */
    public static function findByToken(string $column, string $plainToken): Builder
    {
        return static::query()->where($column, hash('sha256', $plainToken));
    }

    public function confirmationTokenIsExpired(): bool
    {
        return $this->confirmation_sent_at === null
            || $this->confirmation_sent_at->addDays(self::CONFIRMATION_TTL_DAYS)->isPast();
    }

    /**
     * Emails are matched case-insensitively but stored as typed, so
     * "Person@Example.com" and "person@example.com" can never become two rows.
     */
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
