<?php

use App\Filament\Pages\NewsletterDashboard;
use App\Filament\Pages\NewsletterSettings;
use App\Filament\Resources\NewsletterCampaigns\Pages\CreateNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Pages\ListNewsletterCampaigns;
use app\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Mail\Newsletter\ConfirmationMail;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Jobs\SendNewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSegment;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use Database\Seeders\NewsletterSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('renders every newsletter admin screen', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/admin/newsletter-dashboard',
    '/admin/newsletter-settings',
    '/admin/newsletter-subscribers',
    '/admin/newsletter-campaigns',
    '/admin/newsletter-templates',
    '/admin/newsletter-segments',
]);

it('lists subscribers and filters them by status', function () {
    $active = NewsletterSubscriber::factory()->active()->create();
    $pending = NewsletterSubscriber::factory()->create();

    Livewire::test(ListNewsletterSubscribers::class)
        ->assertCanSeeTableRecords([$active, $pending])
        ->filterTable('status', [SubscriberStatus::Active->value])
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('offers no way to create a subscriber by hand', function () {
    expect(NewsletterSubscriberResource::getPages())
        ->not->toHaveKey('create');
});

it('unsubscribes a subscriber from the table without deleting them', function () {
    $subscriber = NewsletterSubscriber::factory()->active()->create();

    Livewire::test(ListNewsletterSubscribers::class)
        ->callTableAction('unsubscribe', $subscriber);

    $subscriber->refresh();

    expect($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->exists)->toBeTrue();
});

it('resends a confirmation email with a fresh token', function () {
    Mail::fake();

    $subscriber = NewsletterSubscriber::factory()->pending('old-token')->create();

    Livewire::test(ListNewsletterSubscribers::class)
        ->callTableAction('resendConfirmation', $subscriber);

    Mail::assertQueued(ConfirmationMail::class);

    // The old link must stop working once a new one is issued.
    expect($subscriber->fresh()->confirmation_token)->not->toBe(hash('sha256', 'old-token'));
});

it('creates a campaign as a draft attributed to its author', function () {
    Livewire::test(CreateNewsletterCampaign::class)
        ->fillForm([
            'name' => 'October insights',
            'subject' => 'What moves your credit score',
            'content' => '<p>Hello there.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = NewsletterCampaign::query()->sole();

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->created_by)->toBe(auth()->id());
});

it('queues a campaign from the send action without sending in the request', function () {
    Queue::fake();

    NewsletterSubscriber::factory()->active()->count(2)->create();
    $campaign = NewsletterCampaign::factory()->create();

    Livewire::test(ListNewsletterCampaigns::class)
        ->callTableAction('send', $campaign);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Scheduled);

    Queue::assertPushed(SendNewsletterCampaign::class);
});

it('cancels a scheduled campaign', function () {
    $campaign = NewsletterCampaign::factory()->scheduled()->create();

    Livewire::test(ListNewsletterCampaigns::class)
        ->callTableAction('cancel', $campaign);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Cancelled);
});

it('refuses to open the edit page for a campaign that has already been sent', function () {
    $campaign = NewsletterCampaign::factory()->sent()->create();

    $this->get('/admin/newsletter-campaigns/'.$campaign->getKey().'/edit')
        ->assertRedirect('/admin/newsletter-campaigns');
});

it('previews a campaign through the real email layout', function () {
    $campaign = NewsletterCampaign::factory()->create(['content' => '<p>Preview me.</p>']);

    Livewire::test(ListNewsletterCampaigns::class)
        ->mountTableAction('preview', $campaign)
        ->assertSuccessful();
});

it('shows list health on the dashboard', function () {
    NewsletterSubscriber::factory()->active()->count(3)->create();
    NewsletterSubscriber::factory()->count(2)->create();
    NewsletterSubscriber::factory()->unsubscribed()->create();

    $stats = Livewire::test(NewsletterDashboard::class)->assertSuccessful()->instance()->stats();

    expect($stats['Total subscribers']['value'])->toBe(6)
        ->and($stats['Active']['value'])->toBe(3)
        ->and($stats['Pending confirmation']['value'])->toBe(2)
        ->and($stats['Unsubscribed']['value'])->toBe(1);
});

it('reports which pages won subscribers', function () {
    NewsletterSubscriber::factory()->count(2)->create(['source' => 'blog', 'source_url' => '/resources/cibil']);
    NewsletterSubscriber::factory()->create(['source' => 'homepage', 'source_url' => '/']);

    $pages = Livewire::test(NewsletterDashboard::class)->instance()->topPages();

    expect($pages->first()['url'])->toBe('/resources/cibil')
        ->and($pages->first()['total'])->toBe(2)
        ->and($pages->first()['source'])->toBe('Blog article');
});

it('saves newsletter settings and applies them to the public site immediately', function () {
    Livewire::test(NewsletterSettings::class)
        ->fillForm([
            'newsletter_enabled' => true,
            'double_opt_in_enabled' => false,
            'welcome_email_enabled' => false,
            'newsletter_sender_name' => 'FynnEdge Insights',
            'newsletter_sender_email' => 'insights@fynnedge.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('newsletter_sender_name'))->toBe('FynnEdge Insights')
        ->and(App\Modules\Newsletter\Services\NewsletterSettings::doubleOptInEnabled())->toBeFalse();
});

it('hides the whole newsletter section from an admin without the permissions', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor);

    foreach ([
        '/admin/newsletter-subscribers',
        '/admin/newsletter-campaigns',
        '/admin/newsletter-dashboard',
        '/admin/newsletter-settings',
    ] as $path) {
        $this->get($path)->assertForbidden();
    }
});

it('lets a marketing role read subscribers without being able to change settings', function () {
    $marketer = User::factory()->create(['is_admin' => true]);
    $marketer->syncRoles([]);
    $marketer->givePermissionTo(Permission::findOrCreate('ViewAny:NewsletterSubscriber'));

    $this->actingAs($marketer);

    $this->get('/admin/newsletter-subscribers')->assertOk();
    $this->get('/admin/newsletter-settings')->assertForbidden();
});

it('does not let an unauthorized admin send a campaign', function () {
    Queue::fake();

    $campaign = NewsletterCampaign::factory()->create();

    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor);

    Livewire::test(ListNewsletterCampaigns::class)->assertForbidden();

    Queue::assertNothingPushed();
});

it('gives the Marketing role campaign access but not newsletter settings', function () {
    $this->seed(RoleSeeder::class);

    $marketer = User::factory()->create(['is_admin' => true]);
    $marketer->syncRoles(['Marketing']);

    $this->actingAs($marketer);

    $this->get('/admin/newsletter-campaigns')->assertOk();
    $this->get('/admin/newsletter-subscribers')->assertOk();
    $this->get('/admin/newsletter-dashboard')->assertOk();

    // Sender identity and double opt-in decide deliverability for the whole
    // domain — deliberately not a campaign-level permission.
    $this->get('/admin/newsletter-settings')->assertForbidden();

    // A subscriber record is a consent record; Marketing can read but not delete it.
    expect($marketer->can('Delete:NewsletterSubscriber'))->toBeFalse();
});

it('seeds starter segments without inventing any subscribers', function () {
    $this->seed(NewsletterSeeder::class);

    expect(NewsletterSegment::query()->count())->toBe(5)
        ->and(NewsletterTemplate::query()->count())->toBe(1)
        // Consent cannot be seeded.
        ->and(NewsletterSubscriber::query()->count())->toBe(0);

    // Re-running must not duplicate.
    $this->seed(NewsletterSeeder::class);
    expect(NewsletterSegment::query()->count())->toBe(5);
});
