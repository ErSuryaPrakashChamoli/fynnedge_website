<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * database/migrations/2026_11_24_130252_move_blog_author_settings_to_authors_table.php
 * copies the interim settings-based authors into the authors table. RefreshDatabase
 * has already run it against an empty settings table, so each test seeds the legacy
 * settings and runs the migration's up()/down() itself.
 */
function authorSettingsMigration(): object
{
    return require database_path('migrations/2026_11_24_130252_move_blog_author_settings_to_authors_table.php');
}

function seedLegacyAuthors(iterable $articles): array
{
    $priya = (string) Str::uuid();
    $rahul = (string) Str::uuid();

    Setting::set('blog_authors', [
        ['id' => $priya, 'name' => 'Priya Sharma', 'slug' => 'priya-sharma', 'designation' => 'Senior Loan Advisor', 'bio' => 'Helps families borrow well.', 'photo_path' => 'authors/01JPRIYA.jpg', 'profile_url' => 'https://www.linkedin.com/in/priya', 'is_active' => true],
        ['id' => $rahul, 'name' => 'Rahul Mehta', 'slug' => 'rahul-mehta', 'designation' => null, 'bio' => null, 'photo_path' => null, 'profile_url' => null, 'is_active' => false],
    ]);
    Setting::set('blog_article_authors', [
        (string) $articles[0]->public_id => $priya,
        (string) $articles[1]->public_id => $rahul,
        'deleted-article-public-id' => $priya,
    ]);

    return [$priya, $rahul];
}

it('moves every legacy author and assignment into the table, keeping ids, slugs, photos and state', function () {
    $articles = Article::factory()->count(3)->create();
    [$priyaId, $rahulId] = seedLegacyAuthors($articles);

    authorSettingsMigration()->up();

    $priya = Author::query()->where('public_id', $priyaId)->sole();
    $rahul = Author::query()->where('public_id', $rahulId)->sole();

    expect($priya)->name->toBe('Priya Sharma')->slug->toBe('priya-sharma')->designation->toBe('Senior Loan Advisor')
        ->photo_path->toBe('authors/01JPRIYA.jpg')->profile_url->toBe('https://www.linkedin.com/in/priya')->is_active->toBeTrue()
        ->and($rahul)->slug->toBe('rahul-mehta')->is_active->toBeFalse()
        ->and($articles[0]->refresh()->author_id)->toBe($priya->id)
        ->and($articles[1]->refresh()->author_id)->toBe($rahul->id)
        ->and($articles[2]->refresh()->author_id)->toBeNull();

    // The same public URL answers, and the legacy settings are untouched.
    $articles[0]->update(['status' => 'published', 'published_at' => now()->subMinute()]);
    $this->get('/resources/authors/priya-sharma')->assertOk()->assertSeeText($articles[0]->title);

    expect(Setting::get('blog_authors'))->toHaveCount(2)
        ->and(Setting::get('blog_article_authors'))->toHaveCount(3);
});

it('can run twice without duplicating authors or overwriting a newer assignment', function () {
    $articles = Article::factory()->count(2)->create();
    seedLegacyAuthors($articles);

    authorSettingsMigration()->up();
    $other = Author::factory()->create();
    $articles[0]->update(['author_id' => $other->id]);

    authorSettingsMigration()->up();

    expect(Author::query()->count())->toBe(3)
        ->and($articles[0]->refresh()->author_id)->toBe($other->id);
});

it('rolls back only what it copied', function () {
    $articles = Article::factory()->count(2)->create();
    seedLegacyAuthors($articles);
    $unrelated = Author::factory()->create();
    $kept = Article::factory()->create(['author_id' => $unrelated->id]);

    authorSettingsMigration()->up();
    authorSettingsMigration()->down();

    expect(Author::query()->pluck('id')->all())->toBe([$unrelated->id])
        ->and($articles[0]->refresh()->author_id)->toBeNull()
        ->and($kept->refresh()->author_id)->toBe($unrelated->id)
        ->and(Setting::get('blog_authors'))->toHaveCount(2);
});

it('does nothing when there are no legacy authors', function () {
    authorSettingsMigration()->up();

    expect(DB::table('authors')->count())->toBe(0);
});

it('rolls the schema back and forward cleanly', function () {
    $this->artisan('migrate:rollback', ['--path' => [
        'database/migrations/2026_11_24_130252_move_blog_author_settings_to_authors_table.php',
        'database/migrations/2026_11_24_130251_add_author_id_to_articles_table.php',
        'database/migrations/2026_11_24_130249_create_authors_table.php',
    ]])->assertSuccessful();

    expect(Schema::hasTable('authors'))->toBeFalse()
        ->and(Schema::hasColumn('articles', 'author_id'))->toBeFalse();

    $this->artisan('migrate', ['--path' => [
        'database/migrations/2026_11_24_130249_create_authors_table.php',
        'database/migrations/2026_11_24_130251_add_author_id_to_articles_table.php',
        'database/migrations/2026_11_24_130252_move_blog_author_settings_to_authors_table.php',
    ]])->assertSuccessful();

    expect(Schema::hasTable('authors'))->toBeTrue()
        ->and(Schema::hasColumn('articles', 'author_id'))->toBeTrue();
});
