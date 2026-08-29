<?php

use App\Enums\PublishStatus;
use App\Models\Article;

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

it('renders a published article by slug', function () {
    $article = Article::factory()->published()->create(['slug' => 'how-emis-work']);

    $this->get('/resources/how-emis-work')->assertOk()->assertSee($article->title);
});

it('404s for a draft article on the public site', function () {
    Article::factory()->create(['slug' => 'draft-guide', 'status' => PublishStatus::Draft]);

    $this->get('/resources/draft-guide')->assertNotFound();
});
