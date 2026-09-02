<?php

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\CompanyPhotos\Pages\EditCompanyPhoto;
use App\Filament\Resources\Pages\Pages\EditPage as EditCmsPage;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Models\Article;
use App\Models\Banner;
use App\Models\CompanyPhoto;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('versions and restores an article', function () {
    $article = Article::factory()->create(['title' => 'Original title']);
    $article->update(['title' => 'Changed title']);

    $log = $article->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, ['ownerRecord' => $article, 'pageClass' => EditArticle::class])
        ->assertCanSeeTableRecords($article->auditLogs)
        ->callTableAction('restore', $log);

    expect($article->fresh()->title)->toBe('Original title');
});

it('versions and restores a testimonial', function () {
    $testimonial = Testimonial::factory()->create(['customer_name' => 'Original Name']);
    $testimonial->update(['customer_name' => 'Changed Name']);

    $log = $testimonial->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, ['ownerRecord' => $testimonial, 'pageClass' => EditTestimonial::class])
        ->callTableAction('restore', $log);

    expect($testimonial->fresh()->customer_name)->toBe('Original Name');
});

it('versions and restores a CMS page', function () {
    $page = Page::factory()->create(['title' => 'Original page title']);
    $page->update(['title' => 'Changed page title']);

    $log = $page->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditCmsPage::class])
        ->callTableAction('restore', $log);

    expect($page->fresh()->title)->toBe('Original page title');
});

it('versions and restores a banner', function () {
    $banner = Banner::factory()->create(['heading' => 'Original heading']);
    $banner->update(['heading' => 'Changed heading']);

    $log = $banner->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, ['ownerRecord' => $banner, 'pageClass' => EditBanner::class])
        ->callTableAction('restore', $log);

    expect($banner->fresh()->heading)->toBe('Original heading');
});

it('versions and restores a company photo', function () {
    $photo = CompanyPhoto::factory()->create(['caption' => 'Original caption']);
    $photo->update(['caption' => 'Changed caption']);

    $log = $photo->auditLogs()->where('action', 'updated')->sole();

    Livewire::test(RestorableAuditLogsRelationManager::class, ['ownerRecord' => $photo, 'pageClass' => EditCompanyPhoto::class])
        ->callTableAction('restore', $log);

    expect($photo->fresh()->caption)->toBe('Original caption');
});
