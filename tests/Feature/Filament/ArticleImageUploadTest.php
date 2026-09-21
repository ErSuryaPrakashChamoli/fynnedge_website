<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Schemas\HtmlBodyEditor;
use App\Models\Article;
use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

/**
 * The column names actually rendered as table headers. Filament's own
 * assertTableColumnVisible() only checks a column's hidden() condition, so it
 * passes for a column the column manager has toggled off — it cannot tell
 * whether an admin really sees it.
 *
 * @return array<int, string>
 */
function visibleArticleColumns(): array
{
    return array_keys(
        Livewire::test(ListArticles::class)->instance()->getTable()->getVisibleColumns()
    );
}

it('stores the cover image on the public disk, where the model and public views read it back', function () {
    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Guide with a cover',
            'slug' => 'guide-with-a-cover',
            'image_path' => UploadedFile::fake()->image('cover.jpg'),
            'image_alt' => 'A calculator on a desk',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'guide-with-a-cover')->sole();

    expect($article->image_path)->toStartWith('articles/');
    expect($article->image_alt)->toBe('A calculator on a desk');
    Storage::disk('public')->assertExists($article->image_path);
    expect($article->imageUrl())->toContain('/storage/'.$article->image_path);
});

it('saves an article without a cover image, since the image is optional', function () {
    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Plain guide',
            'slug' => 'plain-guide',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()->where('slug', 'plain-guide')->sole();

    expect($article->image_path)->toBeNull();
    expect($article->imageUrl())->toBeNull();
});

it('uploads images inserted in the body editor to the public disk, so their URLs resolve on the site', function () {
    $richEditor = collect(HtmlBodyEditor::make('body', attachmentsDirectory: 'articles/inline'))
        ->first(fn ($component) => $component instanceof RichEditor);

    expect($richEditor->getFileAttachmentsDiskName())->toBe('public');
    expect($richEditor->getFileAttachmentsDirectory())->toBe('articles/inline');
    expect($richEditor->getFileAttachmentsVisibility())->toBe('public');
});

it('lists the most recently created article first in the admin table', function () {
    $older = Article::factory()->create(['title' => 'Older guide', 'created_at' => now()->subDay()]);
    $newer = Article::factory()->create(['title' => 'Newer guide', 'created_at' => now()]);

    Livewire::test(ListArticles::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('shows the published date in the admin table, falling back to the creation date like the public site', function () {
    $dated = Article::factory()->published()->create(['published_at' => Carbon::parse('2026-09-01 10:30:00')]);
    $undated = Article::factory()->published()->create(['published_at' => null, 'created_at' => Carbon::parse('2026-09-15 09:00:00')]);
    $draft = Article::factory()->create(['status' => PublishStatus::Draft]);

    expect(visibleArticleColumns())->toContain('published_at');

    Livewire::test(ListArticles::class)
        ->assertTableColumnFormattedStateSet('published_at', '01 Sep 2026', record: $dated)
        ->assertTableColumnFormattedStateSet('published_at', '15 Sep 2026', record: $undated)
        ->assertTableColumnStateSet('published_at', null, record: $draft);
});

it('shows the created and expiry dates in the admin table', function () {
    $expiring = Article::factory()->published()->create([
        'created_at' => Carbon::parse('2026-09-10 08:15:00'),
        'expires_at' => Carbon::parse('2026-12-31 23:59:00'),
    ]);
    $neverExpires = Article::factory()->published()->create([
        'created_at' => Carbon::parse('2026-09-12 14:00:00'),
        'expires_at' => null,
    ]);

    expect(visibleArticleColumns())->toContain('created_at', 'expires_at');

    Livewire::test(ListArticles::class)
        ->assertTableColumnFormattedStateSet('created_at', '10 Sep 2026', record: $expiring)
        ->assertTableColumnFormattedStateSet('expires_at', '31 Dec 2026', record: $expiring)
        ->assertTableColumnFormattedStateSet('created_at', '12 Sep 2026', record: $neverExpires)
        ->assertTableColumnStateSet('expires_at', null, record: $neverExpires);
});

it('still shows the date columns for an admin whose session stored an older column layout', function () {
    /*
     * Filament persists the whole column layout per user (HasColumnManager),
     * and a column that is absent from that stored array reads back as
     * hidden. So an admin who opened this list before a column existed would
     * never see it, no matter what the table now defaults to.
     */
    session()->put('tables.'.md5(ListArticles::class).'_columns', [
        [
            'type' => 'column',
            'name' => 'title',
            'label' => 'Title',
            'isHidden' => false,
            'isToggled' => true,
            'isToggleable' => false,
            'isToggledHiddenByDefault' => false,
        ],
        [
            'type' => 'column',
            'name' => 'created_at',
            'label' => 'Created at',
            'isHidden' => false,
            'isToggled' => false,
            'isToggleable' => true,
            'isToggledHiddenByDefault' => true,
        ],
    ]);

    expect(visibleArticleColumns())->toContain('published_at', 'created_at', 'expires_at');
});

it('keeps the article list narrow enough for the date columns by hiding the slug, while still searching it', function () {
    Article::factory()->create(['title' => 'Nothing alike', 'slug' => 'emi-basics-guide']);

    expect(visibleArticleColumns())->not->toContain('slug');

    Livewire::test(ListArticles::class)
        ->searchTable('emi-basics-guide')
        ->assertCanSeeTableRecords(Article::query()->where('slug', 'emi-basics-guide')->get());
});
