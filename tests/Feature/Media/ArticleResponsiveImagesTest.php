<?php

use App\Models\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function publishedArticleWithCover(string $name): Article
{
    Storage::disk('public')->putFileAs('articles', UploadedFile::fake()->image($name, 1200, 675), $name);

    return Article::factory()->published()->create(['image_path' => "articles/{$name}", 'show_on_home' => true]);
}

it('serves an article\'s cover from its WebP copies, sized from the upload and never lazy', function () {
    Storage::fake('public');
    $article = publishedArticleWithCover('cover.jpg');

    $html = $this->get('/resources/'.$article->slug)->assertOk()->getContent();

    expect($html)->toContain('<source type="image/webp" srcset="/storage/responsive/articles/cover-480.webp 480w, /storage/responsive/articles/cover-768.webp 768w, /storage/responsive/articles/cover-1080.webp 1080w, /storage/responsive/articles/cover-1440.webp 1440w" sizes="(min-width: 1280px) 1216px, (min-width: 1024px) calc(100vw - 64px), calc(100vw - 48px)">')
        ->toMatch('#src="/storage/articles/cover\.jpg"\s+alt="[^"]*"\s+width="1200" height="675"\s+class=#');
});

it('offers the cover\'s WebP copies on the article cards, sized to a card in the grid', function () {
    Storage::fake('public');
    publishedArticleWithCover('card.jpg');

    $this->get('/resources')
        ->assertSee('srcset="/storage/responsive/articles/card-480.webp 480w, /storage/responsive/articles/card-768.webp 768w, /storage/responsive/articles/card-1080.webp 1080w, /storage/responsive/articles/card-1440.webp 1440w" sizes="(min-width: 1280px) 332px, (min-width: 1024px) calc(33vw - 95px), (min-width: 640px) calc(50vw - 94px), calc(100vw - 98px)"', false);
});
