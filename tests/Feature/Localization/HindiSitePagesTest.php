<?php

use App\Models\Article;
use App\Models\ContactEnquiry;
use App\Models\GrievanceLevel;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SocialVideo;
use App\Models\VideoTestimonial;
use App\Support\Contact\OfficeMap;
use App\Support\Localization\SiteLocale;

beforeEach(function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi');
});

it('renders the home page interface and default hero copy in Hindi', function () {
    $this->get('/')
        ->assertSee('लोन को आसान,')
        ->assertSee('भरोसे को मज़बूत।')
        ->assertSee('अपनी पात्रता जांचें')
        ->assertSee('मुफ़्त लोन कैलकुलेटर')
        ->assertSee('आवेदन से पहले अपना क्रेडिट स्कोर जानें')
        ->assertDontSee('Simplifying loans.')
        ->assertDontSee('Explore Loan Products');
});

it('renders the home page video sections in Hindi around the stored names', function () {
    VideoTestimonial::factory()->onEveryPage()->floating()->create(['customer_name' => 'Meera Iyer']);
    SocialVideo::factory()->create();

    $this->get('/')
        ->assertSee('असली ग्राहक। असली कहानियां।')
        ->assertSee('Meera की कहानी देखें')
        ->assertSee('हर रील में लोन की साफ़ जानकारी।')
        ->assertDontSee('Real customers. Real stories.');
});

it('renders the contact form in Hindi with the admin Hindi office address', function () {
    Setting::set('contact_address', 'Sector 2, Noida');
    Setting::set('contact_address_hi', 'सेक्टर 2, नोएडा');

    $this->get('/contact')
        ->assertSee('हमसे बात करें')
        ->assertSee('संदेश भेजें')
        ->assertSee('सेक्टर 2, नोएडा')
        ->assertDontSee('Sector 2, Noida')
        ->assertDontSee('Send message');
});

it('thanks a Hindi visitor in Hindi but stores the enquiry source in English', function () {
    $response = $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '',
        'message' => 'Please email me about home loans.',
    ]);

    $response->assertRedirect('/contact');
    $response->assertSessionHas('statusTitle', 'आपकी लोन क्वेरी सफलतापूर्वक सबमिट हो गई है! 🎉');
    expect(ContactEnquiry::query()->sole()->enquiry_source)->toBe('Contact Page');
});

it('rejects an invalid contact phone with a Hindi message', function () {
    $this->from('/contact')->post('/contact', [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'phone' => '12345',
        'message' => 'Hello',
    ])->assertSessionHasErrors(['phone' => 'कृपया 10 अंकों का सही मोबाइल नंबर डालें, या फ़ोन खाली छोड़ दें।']);

    expect(ContactEnquiry::query()->count())->toBe(0);
});

it('renders the empty-state listing pages in Hindi', function (string $routeName, string $hindi, string $english) {
    $this->get(route($routeName))
        ->assertSee($hindi)
        ->assertDontSee($english);
})->with([
    'partners' => ['partners.index', 'हमारी पार्टनर सूची अपडेट हो रही है। जल्द ही दोबारा देखें।', 'Our partner list is being updated.'],
    'faqs' => ['faqs.index', 'अक्सर पूछे जाने वाले सवाल', 'Frequently asked questions'],
    'resources' => ['resources.index', 'लोन, पात्रता और आवेदन प्रक्रिया पर आसान भाषा में गाइड।', 'Plain-language guides'],
]);

it('renders the careers empty state in Hindi with its contact link intact', function () {
    Page::factory()->published()->create(['slug' => 'careers']);

    $this->get('/careers')
        ->assertSee('खाली पद')
        ->assertSee('<a href="'.route('contact').'" class="text-accent hover:underline">संपर्क</a>', false)
        ->assertDontSee('Open positions');
});

it('renders the grievance matrix headings in Hindi', function () {
    Page::factory()->published()->create(['slug' => 'grievance']);
    GrievanceLevel::factory()->published()->create(['designation' => 'Manager']);

    $this->get('/grievance')
        ->assertSee('समाधान का समय')
        ->assertSee('पद: Manager')
        ->assertDontSee('Turn-around Time');
});

it('dates an article with Hindi month names', function () {
    $article = Article::factory()->published()->create(['published_at' => '2026-10-14 10:00:00']);

    $this->get(route('resources.show', $article))
        ->assertSee('14 अक्टू. 2026')
        ->assertSee('यह लेख पसंद आया?');
});

it('shows the footer office address in Hindi while directions still use the English address', function () {
    app()->setLocale('hi');
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');
    Setting::set('contact_address', 'Sector 2, Noida');
    Setting::set('contact_address_hi', 'सेक्टर 2, नोएडा');

    $card = OfficeMap::forFooter();

    expect($card['address'])->toBe('सेक्टर 2, नोएडा')
        ->and($card['directionsUrl'])->toBe('https://www.google.com/maps/dir/?api=1&destination=Sector%202%2C%20Noida');
});
