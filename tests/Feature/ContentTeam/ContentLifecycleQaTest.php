<?php

use App\Enums\PublishStatus;
use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Article;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Phase 7, Step 14 — a full content-team workflow rehearsal against the
 * isolated test database (RefreshDatabase tears it down after every test;
 * nothing here ever touches a real environment). All content uses an
 * obvious, unmistakable test marker so a reviewer skimming test output
 * never confuses it with real business copy.
 */
const TEST_MARKER = 'TEST — DO NOT PUBLISH';

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('rehearses the full article workflow: create draft, preview, schedule, publish, expire, restore', function () {
    // Create as a draft.
    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => TEST_MARKER.' Article',
            'slug' => 'test-do-not-publish-article',
            'excerpt' => TEST_MARKER,
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'test-do-not-publish-article')->sole();

    // Draft is not publicly visible.
    $this->get('/resources/test-do-not-publish-article')->assertNotFound();

    // Preview works via a signed URL while still a draft.
    $signedPreviewUrl = URL::temporarySignedRoute('resources.show', now()->addMinutes(30), ['article' => $article]);
    $this->get($signedPreviewUrl)->assertOk()->assertSee(TEST_MARKER.' Article');

    // Schedule for the future — still not visible.
    $article->update(['status' => PublishStatus::Published, 'published_at' => now()->addDay()]);
    $this->get('/resources/test-do-not-publish-article')->assertNotFound();

    // Publish immediately — now visible.
    $article->update(['published_at' => now()->subMinute()]);
    $this->get('/resources/test-do-not-publish-article')->assertOk()->assertSee(TEST_MARKER.' Article');

    // Set an expiry in the past — no longer visible.
    $article->update(['expires_at' => now()->subMinute()]);
    $this->get('/resources/test-do-not-publish-article')->assertNotFound();

    // Edit, then restore the previous title from history.
    $article->update(['expires_at' => null, 'title' => TEST_MARKER.' Article (changed)']);
    $log = $article->auditLogs()->where('action', 'updated')->whereJsonContainsKey('changes->title')->latest('id')->first();
    expect($log)->not->toBeNull();

    Livewire::test(RestorableAuditLogsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ])->callTableAction('restore', $log);

    expect($article->fresh()->title)->toBe(TEST_MARKER.' Article');

    // Clean up (belt-and-braces — RefreshDatabase already isolates this).
    $article->delete();
    expect(Article::query()->where('slug', 'test-do-not-publish-article')->exists())->toBeFalse();
});

it('rehearses the banner workflow: image upload, alt text, CTA, ordering, scheduling', function () {
    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('test-banner.jpg'),
            'image_alt' => TEST_MARKER.' banner alt text',
            'heading' => TEST_MARKER.' Banner',
            'cta_label' => TEST_MARKER.' CTA',
            'cta_url' => '/contact',
            'sort_order' => 99,
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->where('heading', TEST_MARKER.' Banner')->sole();

    expect($banner->imageUrl())->not->toBeNull();
    Storage::disk('public')->assertExists($banner->image_path);
    expect($banner->image_alt)->toBe(TEST_MARKER.' banner alt text');
    expect($banner->sort_order)->toBe(99);

    // Draft banner does not appear on the homepage.
    $this->get('/')->assertOk()->assertDontSee(TEST_MARKER.' Banner');

    // Publish and schedule an expiry.
    $banner->update(['status' => PublishStatus::Published, 'expires_at' => now()->addDay()]);
    $this->get('/')->assertOk()->assertSee(TEST_MARKER.' Banner')->assertSee(TEST_MARKER.' CTA');

    // Expire it.
    $banner->update(['expires_at' => now()->subMinute()]);
    $this->get('/')->assertOk()->assertDontSee(TEST_MARKER.' Banner');

    // Clean up the record. Deleting a Banner deliberately does NOT cascade-delete
    // its image file (see Media Governance, Phase 5/6: never silently remove a
    // file) — the now-orphaned file is left for an admin to review and remove
    // via Media Governance's "unused" detection, not auto-deleted here.
    $banner->delete();
    expect(Banner::query()->where('heading', TEST_MARKER.' Banner')->exists())->toBeFalse();
});
