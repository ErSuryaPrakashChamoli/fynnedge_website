<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Livewire\Livewire;

it('shows the three newest published articles switched on for the home page', function () {
    foreach (range(1, 4) as $day) {
        Article::factory()->published()->create(['title' => "Guide {$day}", 'published_at' => now()->subDays($day)]);
    }

    $this->get('/')
        ->assertOk()
        ->assertSee('Latest articles')
        ->assertSeeInOrder(['Guide 1', 'Guide 2', 'Guide 3'])
        ->assertDontSee('Guide 4');
});

it('leaves out articles switched off for the home page, and drafts', function () {
    Article::factory()->published()->create(['title' => 'Shown Guide']);
    Article::factory()->published()->create(['title' => 'Hidden Guide', 'show_on_home' => false]);
    Article::factory()->create(['title' => 'Draft Guide', 'status' => PublishStatus::Draft]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Shown Guide')
        ->assertDontSee('Hidden Guide')
        ->assertDontSee('Draft Guide');
});

it('hides the section when no article is switched on for the home page', function () {
    Article::factory()->published()->create(['show_on_home' => false]);

    $this->get('/')->assertOk()->assertDontSee('Latest articles');
});

it('lets an admin switch an article off the home page from the form and the list', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateArticle::class)
        ->assertSchemaStateSet(['show_on_home' => true])
        ->fillForm([
            'title' => 'Admin Guide',
            'slug' => 'admin-guide',
            'status' => PublishStatus::Draft->value,
            'show_on_home' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'admin-guide')->sole();
    expect($article->show_on_home)->toBeFalse();

    Livewire::test(ListArticles::class)
        ->call('updateTableColumnState', 'show_on_home', (string) $article->getKey(), true);

    expect($article->refresh()->show_on_home)->toBeTrue();
});
