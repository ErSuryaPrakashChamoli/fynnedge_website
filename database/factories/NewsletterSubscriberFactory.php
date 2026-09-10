<?php

namespace Database\Factories;

use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => SubscriberStatus::Pending,
            'source' => SubscriptionSource::Website->value,
            'source_url' => '/',
            'subscribed_at' => now(),
            'consent_at' => now(),
            'consent_ip' => fake()->ipv4(),
        ];
    }

    /**
     * A confirmed subscriber with a usable unsubscribe token. The plaintext is
     * the well-known value below so tests can build the URL without reaching
     * into the hashing — production tokens are always random.
     */
    public function active(string $unsubscribeToken = 'test-unsubscribe-token'): static
    {
        return $this->state([
            'status' => SubscriberStatus::Active,
            'confirmed_at' => now(),
            'unsubscribe_token' => hash('sha256', $unsubscribeToken),
        ]);
    }

    public function pending(string $confirmationToken = 'test-confirmation-token'): static
    {
        return $this->state([
            'status' => SubscriberStatus::Pending,
            'confirmation_token' => hash('sha256', $confirmationToken),
            'confirmation_sent_at' => now(),
        ]);
    }

    public function unsubscribed(string $unsubscribeToken = 'test-unsubscribe-token'): static
    {
        return $this->state([
            'status' => SubscriberStatus::Unsubscribed,
            'confirmed_at' => now()->subMonth(),
            'unsubscribed_at' => now(),
            'unsubscribe_token' => hash('sha256', $unsubscribeToken),
        ]);
    }

    public function fromSource(SubscriptionSource $source, ?string $url = null): static
    {
        return $this->state([
            'source' => $source->value,
            'source_url' => $url ?? '/'.Str::slug(fake()->words(3, true)),
        ]);
    }
}
