<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Models\Article;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

it('does not publicly expose a loan product scheduled to publish in the future', function () {
    $loanProduct = LoanProduct::factory()->published()->create([
        'slug' => 'future-product',
        'published_at' => now()->addDay(),
    ]);

    $this->get("/loans/{$loanProduct->slug}")->assertNotFound();
});

it('stops showing a loan product once it has expired', function () {
    $loanProduct = LoanProduct::factory()->published()->create([
        'slug' => 'expired-product',
        'expires_at' => now()->subMinute(),
    ]);

    $this->get("/loans/{$loanProduct->slug}")->assertNotFound();
});

it('does not publicly expose an article scheduled to publish in the future', function () {
    $article = Article::factory()->published()->create([
        'slug' => 'future-article',
        'published_at' => now()->addDay(),
    ]);

    $this->get("/resources/{$article->slug}")->assertNotFound();
});

it('lets an admin preview a draft loan product via a signed URL, but not via a plain URL', function () {
    $loanProduct = LoanProduct::factory()->create(['slug' => 'draft-preview-product']);

    $this->get("/loans/{$loanProduct->slug}")->assertNotFound();

    $signedUrl = URL::temporarySignedRoute('loans.show', now()->addMinutes(30), ['loanProduct' => $loanProduct]);

    $this->get($signedUrl)->assertOk()->assertSee($loanProduct->name);
});

it('rejects an expired preview signature', function () {
    $loanProduct = LoanProduct::factory()->create(['slug' => 'expired-preview-product']);

    $signedUrl = URL::temporarySignedRoute('loans.show', now()->subMinute(), ['loanProduct' => $loanProduct]);

    $this->get($signedUrl)->assertNotFound();
});

it('exposes a working preview action on the loan product edit page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->assertActionExists('preview');
});

it('exposes a working preview action on the article edit page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $article = Article::factory()->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionExists('preview');
});
