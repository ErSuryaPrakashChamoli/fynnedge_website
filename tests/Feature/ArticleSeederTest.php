<?php

use App\Enums\PublishStatus;
use App\Models\Article;
use Database\Seeders\ArticleSeeder;

it('seeds a published loan knowledge-base article for every core topic', function () {
    (new ArticleSeeder)->run();

    $slugs = [
        'how-emis-work',
        'credit-score-and-why-it-matters',
        'what-is-foir',
        'documents-needed-for-a-personal-loan',
        'personal-loan-vs-loan-against-property',
        'fixed-vs-floating-interest-rates',
        'improve-loan-approval-chances',
        'understanding-loan-processing-fees',
        'home-loan-eligibility-explained',
        'business-loan-eligibility-for-self-employed',
    ];

    expect(Article::query()->count())->toBe(count($slugs));

    foreach ($slugs as $slug) {
        $article = Article::query()->where('slug', $slug)->first();

        expect($article)->not->toBeNull("Missing seeded article [{$slug}]");
        expect($article->status)->toBe(PublishStatus::Published);
        expect($article->published_at)->not->toBeNull();
        expect($article->title)->not->toBeEmpty();
        expect($article->excerpt)->not->toBeEmpty();
        expect($article->body)->toContain('<h2>');
        expect($article->body)->not->toContain('{{');
    }
});

it('is idempotent — running it twice does not duplicate articles', function () {
    (new ArticleSeeder)->run();
    (new ArticleSeeder)->run();

    expect(Article::query()->count())->toBe(10);
});

it('only cross-links articles by real relative paths, not unresolved Blade syntax', function () {
    (new ArticleSeeder)->run();

    Article::query()->get()->each(function (Article $article) {
        if (! str_contains($article->body, '<a href="/resources/')) {
            return;
        }

        preg_match_all('/<a href="\/resources\/([a-z0-9-]+)"/', $article->body, $matches);

        foreach ($matches[1] as $linkedSlug) {
            expect(Article::query()->where('slug', $linkedSlug)->exists())
                ->toBeTrue("Article [{$article->slug}] links to a non-existent article [{$linkedSlug}]");
        }
    });
});
