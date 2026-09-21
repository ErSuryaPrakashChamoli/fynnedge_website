<?php

use App\Enums\PublishStatus;
use App\Models\Article;
use Illuminate\Support\Facades\Storage;

it('shows the Resources nav link once an article is published', function () {
    Article::factory()->published()->create();

    $this->get('/')->assertOk()->assertSee(route('resources.index'), false);
});

it('renders the resources index with only published articles', function () {
    $published = Article::factory()->published()->create(['title' => 'How EMIs Work']);
    $draft = Article::factory()->create(['title' => 'Draft Guide', 'status' => PublishStatus::Draft]);

    $response = $this->get('/resources');

    $response->assertOk();
    $response->assertSee($published->title);
    $response->assertDontSee($draft->title);
});

it('lists the newest article first even when it has no publish date', function () {
    Article::factory()->published()->create(['title' => 'Older Dated Guide', 'published_at' => now()->subDay()]);
    Article::factory()->published()->create(['title' => 'Newest Undated Guide', 'published_at' => null]);

    $this->get('/resources')->assertOk()->assertSeeInOrder(['Newest Undated Guide', 'Older Dated Guide']);
});

it('shows a date on an article card even when no publish date was set', function () {
    $article = Article::factory()->published()->create(['published_at' => null, 'created_at' => now()->subDays(3)]);

    $this->get('/resources')->assertOk()->assertSee($article->created_at->format('d M Y'));
});

it('shows the cover image on the resources card and the article page when one is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('articles/cover.jpg', 'fake-image');

    $article = Article::factory()->published()->create([
        'slug' => 'guide-with-cover',
        'image_path' => 'articles/cover.jpg',
        'image_alt' => 'Coins stacked beside a notebook',
    ]);

    $this->get('/resources')
        ->assertOk()
        ->assertSee($article->imageUrl(), false)
        ->assertSee('alt="Coins stacked beside a notebook"', false);

    $this->get('/resources/guide-with-cover')
        ->assertOk()
        ->assertSee($article->imageUrl(), false)
        ->assertSee('alt="Coins stacked beside a notebook"', false);
});

it('renders a published article by slug', function () {
    $article = Article::factory()->published()->create(['slug' => 'how-emis-work']);

    $this->get('/resources/how-emis-work')->assertOk()->assertSee($article->title);
});

it('404s for a draft article on the public site', function () {
    Article::factory()->create(['slug' => 'draft-guide', 'status' => PublishStatus::Draft]);

    $this->get('/resources/draft-guide')->assertNotFound();
});
