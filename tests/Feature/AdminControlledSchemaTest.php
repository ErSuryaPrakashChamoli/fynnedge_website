<?php

use App\Enums\PublishStatus;
use App\Filament\Pages\StructuredData;
use App\Models\Page;
use App\Models\SchemaTemplate;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Models\User;
use App\Support\Seo\SchemaGraph;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

/**
 * @return array<int, array<string, mixed>>
 */
function adminSchemaGraphNodes(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();

    $graph = collect(explode('<script type="application/ld+json">', $html))
        ->skip(1)
        ->map(fn (string $chunk) => json_decode(explode('</script>', $chunk)[0], true))
        ->first(fn (?array $data) => isset($data['@graph']));

    return $graph['@graph'] ?? [];
}

/**
 * @param  array<int, array<string, mixed>>  $nodes
 */
function adminSchemaNodeOfType(array $nodes, string $type): ?array
{
    return collect($nodes)->first(fn (array $node): bool => in_array($type, (array) ($node['@type'] ?? []), true));
}

function publishedPage(string $slug): Page
{
    return Page::factory()->create([
        'slug' => $slug,
        'status' => PublishStatus::Published,
        'published_at' => now(),
    ]);
}

it('types the Organization node from the admin-entered list', function () {
    Setting::set('schema_organization_types', "LocalBusiness\nProfessionalService");

    $organization = adminSchemaNodeOfType(adminSchemaGraphNodes('/'), 'LocalBusiness');

    expect($organization['@type'])->toBe(['Organization', 'LocalBusiness', 'ProfessionalService']);
});

it('keeps Organization in the type list even when the admin leaves it out', function () {
    Setting::set('schema_organization_types', 'LocalBusiness');

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/'), 'Organization'))->not->toBeNull();
});

it('collapses a single admin-entered type to a string rather than a one-item array', function () {
    Setting::set('schema_organization_types', 'Organization');

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/'), 'Organization')['@type'])->toBe('Organization');
});

it('falls back to the shipped default types when the setting is blank', function () {
    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/'), 'Organization')['@type'])
        ->toBe(SchemaGraph::DEFAULT_ORGANIZATION_TYPES);
});

it('merges admin-entered extra properties into the generated nodes', function () {
    Setting::set('schema_organization_extra', ['foundingDate' => '2023-04-01', 'slogan' => 'Simplifying loans']);
    Setting::set('schema_website_extra', ['copyrightYear' => 2026]);

    $nodes = adminSchemaGraphNodes('/');

    expect(adminSchemaNodeOfType($nodes, 'Organization')['foundingDate'])->toBe('2023-04-01');
    expect(adminSchemaNodeOfType($nodes, 'Organization')['slogan'])->toBe('Simplifying loans');
    expect(adminSchemaNodeOfType($nodes, 'WebSite')['copyrightYear'])->toBe(2026);
});

it('lets an admin override a generated property but never the @id the graph references', function () {
    Setting::set('schema_organization_extra', [
        '@id' => 'https://example.com/#hijacked',
        'name' => 'Renamed in the panel',
    ]);

    $organization = adminSchemaNodeOfType(adminSchemaGraphNodes('/'), 'Organization');

    expect($organization['name'])->toBe('Renamed in the panel');
    expect($organization['@id'])->toBe(SchemaGraph::organizationId());
});

it('types every WebPage node from the sitewide default setting', function () {
    Setting::set('schema_default_page_type', 'CollectionPage');
    publishedPage('terms');

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/terms'), 'CollectionPage'))->not->toBeNull();
});

it('lets a record override both the sitewide default and the type its view hardcodes', function () {
    Setting::set('schema_default_page_type', 'WebPage');

    $page = publishedPage('terms');
    $page->seoMeta()->save(new SeoMeta(['page_type' => 'ProfilePage']));

    $about = publishedPage('about');
    $about->seoMeta()->save(new SeoMeta(['page_type' => 'ItemPage']));

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/terms'), 'ProfilePage'))->not->toBeNull();
    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/about'), 'ItemPage'))->not->toBeNull();
    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/about'), 'AboutPage'))->toBeNull();
});

it('keeps the view-declared type when a record sets none', function () {
    publishedPage('about');

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/about'), 'AboutPage'))->not->toBeNull();
});

it('renders an attached schema template into the same graph with placeholders filled in', function () {
    $template = SchemaTemplate::factory()->create([
        'schema_type' => 'HowTo',
        'body' => [
            '@type' => 'HowTo',
            'name' => 'How to apply: {{ title }}',
            'url' => '{{ url }}',
            'publisher' => ['@id' => '{{ organization_id }}'],
        ],
    ]);

    $page = publishedPage('terms');
    $page->seoMeta()->save(new SeoMeta(['title' => 'Terms of use', 'schema_template_id' => $template->id]));

    $howTo = adminSchemaNodeOfType(adminSchemaGraphNodes('/terms'), 'HowTo');

    expect($howTo['name'])->toBe('How to apply: Terms of use');
    expect($howTo['url'])->toBe(url('/terms'));
    expect($howTo['publisher'])->toBe(['@id' => SchemaGraph::organizationId()]);
    expect($howTo['@id'])->toBe(url('/terms').'#template-'.$template->id);
});

it('drops a template property whose placeholder has nothing to fill it', function () {
    $template = SchemaTemplate::factory()->create([
        'body' => ['@type' => 'HowTo', 'name' => '{{ title }}', 'image' => '{{ image }}'],
    ]);

    $page = publishedPage('terms');
    $page->seoMeta()->save(new SeoMeta(['schema_template_id' => $template->id]));

    Setting::set('seo_default_og_image', null);

    $howTo = adminSchemaNodeOfType(adminSchemaGraphNodes('/terms'), 'HowTo');

    expect($howTo)->not->toBeNull();
    expect($howTo)->not->toHaveKey('image');
});

it('stops rendering a template everywhere once it is deactivated', function () {
    $template = SchemaTemplate::factory()->inactive()->create();

    $page = publishedPage('terms');
    $page->seoMeta()->save(new SeoMeta(['schema_template_id' => $template->id]));

    expect(adminSchemaNodeOfType(adminSchemaGraphNodes('/terms'), 'HowTo'))->toBeNull();
});

it('keeps the graph intact when a template is deleted out from under a page', function () {
    $template = SchemaTemplate::factory()->create();

    $page = publishedPage('terms');
    $page->seoMeta()->save(new SeoMeta(['schema_template_id' => $template->id]));

    $template->forceDelete();

    $nodes = adminSchemaGraphNodes('/terms');

    expect(adminSchemaNodeOfType($nodes, 'WebPage'))->not->toBeNull();
    expect(adminSchemaNodeOfType($nodes, 'HowTo'))->toBeNull();
});

it('hides the Structured Data page from a role that has not been granted it', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);

    $this->actingAs($user);

    expect(StructuredData::canAccess())->toBeFalse();
    $this->get(StructuredData::getUrl())->assertForbidden();
});

it('opens the Structured Data page to any role an admin grants the permission to', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);
    $user->givePermissionTo(Permission::findOrCreate('View:StructuredData'));

    $this->actingAs($user);

    expect(StructuredData::canAccess())->toBeTrue();
    $this->get(StructuredData::getUrl())->assertOk();
});

it('saves the sitewide graph settings from the panel', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    Livewire\Livewire::test(StructuredData::class)
        ->fillForm([
            'schema_organization_types' => "Organization\nLocalBusiness",
            'schema_website_type' => 'WebSite',
            'schema_default_page_type' => 'CollectionPage',
            'schema_language' => 'en-IN',
            'schema_organization_extra' => '{"foundingDate": "2023-04-01"}',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('schema_default_page_type'))->toBe('CollectionPage');
    expect(Setting::get('schema_organization_extra'))->toBe(['foundingDate' => '2023-04-01']);
});

it('rejects extra properties that are not valid JSON', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    Livewire\Livewire::test(StructuredData::class)
        ->fillForm(['schema_organization_extra' => 'not json at all'])
        ->call('save')
        ->assertHasFormErrors(['schema_organization_extra']);
});

it('keeps schema templates out of reach of a role an admin has not granted them to', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);

    $this->actingAs($user)->get('/admin/schema-templates')->assertForbidden();
});

it('opens schema templates to a role once an admin grants the permission', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['SEO']);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:SchemaTemplate'));

    $this->actingAs($user)->get('/admin/schema-templates')->assertOk();
});
