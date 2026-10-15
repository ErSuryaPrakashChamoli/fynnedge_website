<?php

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Filament\Resources\CompanyPhotos\Pages\CreateCompanyPhoto;
use App\Filament\Resources\EligibilityRuleSets\Pages\EditEligibilityRuleSet;
use App\Filament\Resources\EligibilityRuleSets\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\JourneyDefinitions\Pages\EditJourneyDefinition;
use App\Filament\Resources\JourneyDefinitions\RelationManagers\StepsRelationManager;
use App\Filament\Resources\LoanLandingPageContent\Pages\EditLoanLandingPageContent;
use App\Filament\Resources\LoanLandingPages\Pages\CreateLoanLandingPage;
use App\Filament\Resources\LoanLandingPages\Pages\EditLoanLandingPage;
use App\Filament\Resources\LoanProductContent\Pages\EditLoanProductContent;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Filament\Resources\LoanProductSeo\Pages\EditLoanProductSeo;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\CompanyPhoto;
use App\Models\Faq;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\User;
use App\Modules\Eligibility\Models\EligibilityRule;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Every admin form that edits a translatable model ends with a collapsed
 * "Hindi translation" section whose fields save to the `{column}_hi`
 * siblings HasTranslations serves to Hindi visitors.
 */

function signInForHindiFormsAs(string $role): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return $user;
}

it('saves a page\'s Hindi title, excerpt and body and shows them again when the page is reopened', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $page = Page::factory()->create(['title' => 'About us']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'title_hi' => 'हमारे बारे में',
            'excerpt_hi' => 'फिनएज की कहानी',
            'body_hi' => '<p>हम सही ऋण चुनने में आपकी मदद करते हैं।</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->title)->toBe('About us')
        ->and($page->title_hi)->toBe('हमारे बारे में')
        ->and($page->body_hi)->toContain('हम सही ऋण चुनने में आपकी मदद करते हैं।');

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertSchemaStateSet([
            'title_hi' => 'हमारे बारे में',
            'excerpt_hi' => 'फिनएज की कहानी',
        ]);
});

it('gives a Hindi excerpt half as much room again as the English one, and no more', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['excerpt_hi' => str_repeat('अ', 241)])
        ->call('save')
        ->assertHasFormErrors(['excerpt_hi' => 'max']);

    expect($page->refresh()->excerpt_hi)->toBeNull();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['excerpt_hi' => str_repeat('अ', 240)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->excerpt_hi)->toBe(str_repeat('अ', 240));
});

it('saves a loan product\'s Hindi feature list and raw HTML body and reopens them unchanged', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $loanProduct = LoanProduct::factory()->create(['body' => '<p>English body</p>']);
    $body = '<section class="highlight"><p>तुरंत स्वीकृति</p></section>';

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'features_hi' => ['कोई गारंटी नहीं', 'उसी दिन वितरण'],
            'body_hi_html_mode' => true,
            'body_hi_html' => $body,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->features_hi)->toBe(['कोई गारंटी नहीं', 'उसी दिन वितरण'])
        ->and($loanProduct->body_hi)->toBe($body)
        ->and($loanProduct->body)->toBe('<p>English body</p>');

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->assertSchemaStateSet([
            'features_hi' => ['कोई गारंटी नहीं', 'उसी दिन वितरण'],
            'body_hi_html_mode' => true,
            'body_hi_html' => $body,
        ]);
});

it('rejects a typed interest rate in the Hindi calculator rate note, like the English one', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(EditLoanProduct::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['interest_rate_note_hi' => 'ब्याज दर 10.49% p.a. से'])
        ->call('save')
        ->assertHasFormErrors(['interest_rate_note_hi']);

    expect($loanProduct->refresh()->interest_rate_note_hi)->toBeNull();
});

it('saves a location page\'s Hindi title, city and excerpt and shows them again when it is reopened', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $landingPage = LoanLandingPage::factory()->create(['group' => LandingPageGroup::ByLocation, 'city' => 'Delhi']);

    Livewire::test(EditLoanLandingPage::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'title_hi' => 'दिल्ली में पर्सनल लोन',
            'city_hi' => 'दिल्ली',
            'excerpt_hi' => 'दिल्ली में तुरंत पर्सनल लोन',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($landingPage->refresh()->city_hi)->toBe('दिल्ली');

    Livewire::test(EditLoanLandingPage::class, ['record' => $landingPage->getRouteKey()])
        ->assertSchemaStateSet([
            'title_hi' => 'दिल्ली में पर्सनल लोन',
            'city_hi' => 'दिल्ली',
            'excerpt_hi' => 'दिल्ली में तुरंत पर्सनल लोन',
        ]);
});

it('shows the Hindi city field only on a location page, like the English one', function (LandingPageGroup $group, bool $isVisible) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $component = Livewire::test(CreateLoanLandingPage::class)
        ->fillForm(['group' => $group->value]);

    $isVisible ? $component->assertFormFieldVisible('city_hi') : $component->assertFormFieldHidden('city_hi');
})->with([
    'location page' => [LandingPageGroup::ByLocation, true],
    'amount page' => [LandingPageGroup::ByAmount, false],
]);

it('saves a general FAQ\'s Hindi question and answer and shows them again when it is reopened', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $faq = Faq::factory()->create();

    Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
        ->fillForm([
            'question_hi' => 'क्या मुझे गारंटर चाहिए?',
            'answer_hi' => 'नहीं, पर्सनल लोन के लिए गारंटर की आवश्यकता नहीं है।',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($faq->refresh()->question_hi)->toBe('क्या मुझे गारंटर चाहिए?');

    Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
        ->assertSchemaStateSet([
            'question_hi' => 'क्या मुझे गारंटर चाहिए?',
            'answer_hi' => 'नहीं, पर्सनल लोन के लिए गारंटर की आवश्यकता नहीं है।',
        ]);
});

it('saves the Hindi SEO title and description on the record\'s SEO metadata', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm([
            'seoMeta' => [
                'title_hi' => 'हमारे बारे में | FynnEdge',
                'description_hi' => 'जानिए FynnEdge कैसे काम करता है।',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->seoMeta->title_hi)->toBe('हमारे बारे में | FynnEdge')
        ->and($page->seoMeta->description_hi)->toBe('जानिए FynnEdge कैसे काम करता है।');

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertSchemaStateSet([
            'seoMeta.title_hi' => 'हमारे बारे में | FynnEdge',
            'seoMeta.description_hi' => 'जानिए FynnEdge कैसे काम करता है।',
        ]);
});

it('lets Marketing save the Hindi version of the loan product content it may edit', function () {
    signInForHindiFormsAs('Marketing');
    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(EditLoanProductContent::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm([
            'summary_hi' => 'तेज़ और आसान पर्सनल लोन',
            'benefits_hi' => ['उसी दिन वितरण'],
            'body_hi_html_mode' => true,
            'body_hi_html' => '<div class="note"><p>कोई छिपा शुल्क नहीं</p></div>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $loanProduct->refresh();
    expect($loanProduct->summary_hi)->toBe('तेज़ और आसान पर्सनल लोन')
        ->and($loanProduct->benefits_hi)->toBe(['उसी दिन वितरण'])
        ->and($loanProduct->body_hi)->toBe('<div class="note"><p>कोई छिपा शुल्क नहीं</p></div>');
});

it('never lets the Marketing content editor write the Hindi loan product name, since it may not change the English one', function () {
    $loanProduct = LoanProduct::factory()->create(['name_hi' => 'पर्सनल लोन']);

    $filtered = (fn () => $this->mutateFormDataBeforeSave([
        'summary_hi' => 'नया सारांश',
        'name_hi' => 'छेड़ा गया नाम',
    ]))->call(new EditLoanProductContent);
    $loanProduct->update($filtered);

    expect($loanProduct->refresh()->summary_hi)->toBe('नया सारांश')
        ->and($loanProduct->name_hi)->toBe('पर्सनल लोन');
});

it('lets Marketing save the Hindi version of the landing page content it may edit', function () {
    signInForHindiFormsAs('Marketing');
    $landingPage = LoanLandingPage::factory()->create();

    Livewire::test(EditLoanLandingPageContent::class, ['record' => $landingPage->getRouteKey()])
        ->fillForm([
            'title_hi' => 'पर्सनल लोन ₹5 लाख',
            'excerpt_hi' => 'मिनटों में पात्रता जानें',
            'cta_label_hi' => 'पात्रता जांचें',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $landingPage->refresh();
    expect($landingPage->title_hi)->toBe('पर्सनल लोन ₹5 लाख')
        ->and($landingPage->excerpt_hi)->toBe('मिनटों में पात्रता जानें')
        ->and($landingPage->cta_label_hi)->toBe('पात्रता जांचें');
});

it('lets SEO save the Hindi SEO title through the loan product SEO editor', function () {
    signInForHindiFormsAs('SEO');
    $loanProduct = LoanProduct::factory()->create();

    Livewire::test(EditLoanProductSeo::class, ['record' => $loanProduct->getRouteKey()])
        ->fillForm(['seoMeta' => ['title_hi' => 'पर्सनल लोन ऑनलाइन']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($loanProduct->refresh()->seoMeta->title_hi)->toBe('पर्सनल लोन ऑनलाइन');
});

it('saves a journey step\'s Hindi title and an existing select field\'s Hindi options from the step editor', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $definition = JourneyDefinition::create([
        'loan_product_id' => LoanProduct::factory()->create()->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active,
    ]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    $field = JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'employment_type', 'label' => 'Employment type',
        'type' => FieldType::Select, 'options' => [['value' => 'salaried', 'label' => 'Salaried']], 'order' => 1,
    ]);

    Livewire::test(StepsRelationManager::class, ['ownerRecord' => $definition, 'pageClass' => EditJourneyDefinition::class])
        ->mountTableAction('edit', $step)
        ->assertFormFieldVisible("fields.record-{$field->id}.options_hi")
        ->setTableActionData([
            'title_hi' => 'मूल जानकारी',
            "fields.record-{$field->id}.label_hi" => 'रोज़गार का प्रकार',
            "fields.record-{$field->id}.options_hi" => [['value' => 'salaried', 'label' => 'वेतनभोगी']],
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($step->refresh()->title_hi)->toBe('मूल जानकारी');
    expect($field->refresh()->label_hi)->toBe('रोज़गार का प्रकार')
        ->and($field->options_hi)->toBe([['value' => 'salaried', 'label' => 'वेतनभोगी']]);
});

it('applies the Hindi caption to every photo uploaded in one batch, like the English caption', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateCompanyPhoto::class)
        ->fillForm([
            'photo_paths' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
            'caption' => 'Team offsite',
            'caption_hi' => 'टीम आउटिंग',
            'status' => PublishStatus::Published->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CompanyPhoto::query()->pluck('caption_hi')->all())->toBe(['टीम आउटिंग', 'टीम आउटिंग']);
});

it('saves an eligibility rule\'s Hindi label and customer message from the rule editor', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $rule = EligibilityRule::factory()->create(['label' => 'Minimum age', 'customer_message' => 'You must be at least 21.']);

    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $rule->ruleSet, 'pageClass' => EditEligibilityRuleSet::class])
        ->mountTableAction('edit', $rule)
        ->setTableActionData([
            'label_hi' => 'न्यूनतम आयु',
            'customer_message_hi' => 'आपकी आयु कम से कम 21 वर्ष होनी चाहिए।',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $rule->refresh();
    expect($rule->label_hi)->toBe('न्यूनतम आयु')
        ->and($rule->customer_message_hi)->toBe('आपकी आयु कम से कम 21 वर्ष होनी चाहिए।')
        ->and($rule->label)->toBe('Minimum age');
});
