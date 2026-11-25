<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Article;
use App\Models\Author;
use App\Models\User;
use App\Support\Media\MediaCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function priya(array $overrides = []): Author
{
    return Author::factory()->create([
        'name' => 'Priya Sharma',
        'slug' => 'priya-sharma',
        'designation' => 'Senior Loan Advisor',
        'bio' => 'Ten years helping families borrow well.',
        'profile_url' => 'https://www.linkedin.com/in/priya',
        'photo_path' => 'authors/priya.jpg',
        ...$overrides,
    ]);
}

it('creates an author with a photo at the same admin address as before', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expect(AuthorResource::getUrl('index', isAbsolute: false))->toBe('/admin/authors');

    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Rahul Mehta',
            'designation' => 'Credit Analyst',
            'bio' => 'Explains credit scores in plain words.',
            'photo_path' => UploadedFile::fake()->image('rahul.jpg', 400, 400),
            'profile_url' => 'https://www.linkedin.com/in/rahul',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $author = Author::query()->where('name', 'Rahul Mehta')->sole();

    expect($author)->slug->toBe('rahul-mehta')->designation->toBe('Credit Analyst')->is_active->toBeTrue()
        ->and($author->photo_path)->toStartWith('authors/')
        ->and($author->public_id)->not->toBeEmpty();

    Storage::disk('public')->assertExists($author->photo_path);
});

it('rejects a non-https profile link and a clashing author address', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    priya();

    Livewire::test(CreateAuthor::class)
        ->fillForm(['name' => 'Someone', 'slug' => 'priya-sharma', 'profile_url' => 'http://example.com'])
        ->call('create')
        ->assertHasFormErrors(['profile_url', 'slug']);
});

it('keeps authors to the people who may edit articles', function () {
    $this->seed(RoleSeeder::class);

    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles(['SEO']);
    $this->actingAs($seo)->get(AuthorResource::getUrl('index'))->assertOk();

    $nobody = User::factory()->create(['is_admin' => true]);
    $nobody->syncRoles([]);
    $this->actingAs($nobody)->get(AuthorResource::getUrl('index'))->assertForbidden();
});

it('assigns an author when an article is created and edited', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $priya = priya();
    $rahul = Author::factory()->create(['name' => 'Rahul Mehta']);
    $retired = Author::factory()->inactive()->create(['name' => 'Retired Writer']);

    Livewire::test(CreateArticle::class)
        ->assertFormFieldExists('author_id')
        ->fillForm(['title' => 'Improve your CIBIL score', 'slug' => 'improve-your-cibil-score', 'status' => 'draft', 'author_id' => $priya->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'improve-your-cibil-score')->sole();

    expect($article->author->is($priya))->toBeTrue();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertSchemaStateSet(['author_id' => $priya->id])
        ->fillForm(['author_id' => $rahul->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->author_id)->toBe($rahul->id);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['author_id' => $retired->id])
        ->call('save')
        ->assertHasFormErrors(['author_id']);
});

it('shows the byline, the about-the-author box and a Person author in the structured data', function () {
    $article = Article::factory()->published()->create(['slug' => 'home-loan-tax-benefits', 'author_id' => priya()->id]);

    $response = $this->get('/resources/home-loan-tax-benefits')->assertOk()
        ->assertSeeText('Written by')
        ->assertSeeText('Priya Sharma')
        ->assertSeeText('About the author')
        ->assertSeeText('Ten years helping families borrow well.')
        ->assertSee('/storage/authors/priya.jpg', false)
        ->assertSee(route('resources.authors.show', 'priya-sharma'), false);

    preg_match('/"@type":"Article".*?<\/script>/s', $response->getContent(), $match);

    expect($match[0] ?? '')->toContain('"author":{"@type":"Person","name":"Priya Sharma"');

    $this->get('/resources')->assertOk()->assertSeeText('Priya Sharma');
});

it('lists an author\'s published articles on their page, and hides inactive authors without unlinking', function () {
    $author = priya();
    $published = Article::factory()->published()->create(['title' => 'Published piece', 'author_id' => $author->id]);
    Article::factory()->create(['title' => 'Draft piece', 'author_id' => $author->id]);

    $this->get('/resources/authors/priya-sharma')->assertOk()
        ->assertSeeText('Priya Sharma')
        ->assertSeeText('Published piece')
        ->assertDontSeeText('Draft piece');

    $author->update(['is_active' => false]);

    $this->get('/resources/authors/priya-sharma')->assertNotFound();
    $this->get('/resources/'.$published->slug)->assertOk()->assertDontSeeText('About the author');

    expect($published->refresh()->author_id)->toBe($author->id);
});

it('leaves articles in place, without a byline, when their author is deleted', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $author = priya();
    $article = Article::factory()->published()->create(['author_id' => $author->id]);

    Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])->callAction('delete');

    expect(Author::query()->count())->toBe(0)
        ->and($article->refresh()->exists)->toBeTrue()
        ->and($article->author_id)->toBeNull();
});

it('lists authors with their article counts', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $author = priya();
    Article::factory()->count(2)->create(['author_id' => $author->id]);

    Livewire::test(ListAuthors::class)
        ->assertCanSeeTableRecords([$author])
        ->assertTableColumnStateSet('articles_count', 2, $author);
});

it('counts author photos as used in the media library', function () {
    Storage::fake('public');
    Storage::disk('public')->put('authors/priya.jpg', 'x');
    $author = priya();

    $entry = (new MediaCatalog)->all()->firstWhere('path', 'authors/priya.jpg');

    expect($entry->references)->toHaveCount(1)
        ->and($entry->references->first()->recordLabel)->toBe('Priya Sharma')
        ->and($entry->references->first()->recordEditUrl)->toBe(AuthorResource::getUrl('edit', ['record' => $author]));
});
