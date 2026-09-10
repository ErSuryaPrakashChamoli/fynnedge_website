<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditScoreController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\LoanLandingPageController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NewsletterTrackingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Support\Seo\Sitemap;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/about', AboutController::class)->name('about');
Route::get('/careers', CareerController::class)->name('careers');

foreach (Sitemap::ROUTED_PAGE_SLUGS as $slug) {
    Route::get("/{$slug}", [PageController::class, 'show'])->name($slug)->defaults('slug', $slug);
}

/*
 * Newsletter. The public POST endpoints are throttled (see the `newsletter`
 * limiter in AppServiceProvider); the token-addressed GET routes are not, since
 * they carry a 48-character secret and are followed from an inbox, sometimes by
 * a mail client prefetching on the reader's behalf.
 */
Route::middleware('throttle:newsletter')->group(function (): void {
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
    Route::post('/newsletter/resend-confirmation', [NewsletterController::class, 'resendConfirmation'])->name('newsletter.resend-confirmation');
});

Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::post('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe.post');
Route::get('/newsletter/preferences/{token}', [NewsletterController::class, 'preferences'])->name('newsletter.preferences');
Route::post('/newsletter/preferences/{token}', [NewsletterController::class, 'updatePreferences'])->name('newsletter.preferences.update');
Route::get('/newsletter/track/open/{recipient}/{token}', [NewsletterTrackingController::class, 'open'])->name('newsletter.track.open');
Route::get('/newsletter/track/click/{recipient}/{token}', [NewsletterTrackingController::class, 'click'])->name('newsletter.track.click');

Route::get('/loans', [LoanProductController::class, 'index'])->name('loans.index');
Route::get('/loans/{loanProduct:slug}', [LoanProductController::class, 'show'])->name('loans.show');

Route::get('/eligibility', [JourneyController::class, 'pickProduct'])->name('eligibility.index');

Route::middleware('throttle:public-forms')->group(function (): void {
    Route::get('/loans/{loanProduct:slug}/apply', [JourneyController::class, 'start'])->name('loans.apply');
    Route::get('/journey/{session}', [JourneyController::class, 'show'])->name('journey.show');
    Route::post('/journey/{session}', [JourneyController::class, 'update'])->name('journey.update');
    Route::post('/journey/{session}/back', [JourneyController::class, 'back'])->name('journey.back');
    Route::post('/journey/{session}/phone/send-otp', [JourneyController::class, 'sendPhoneOtp'])->name('journey.phone.send-otp');
    Route::post('/journey/{session}/phone/verify-otp', [JourneyController::class, 'verifyPhoneOtp'])->name('journey.phone.verify-otp');

    Route::get('/credit-score/{bureau}', [CreditScoreController::class, 'show'])->name('credit-score.show');

    Route::post('/eligibility-results/{eligibilityResult}/select', [ApplicationController::class, 'select'])->name('applications.select');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/assistance', [ApplicationController::class, 'chooseAssistance'])->name('applications.assistance');
    Route::post('/applications/{application}/documents', [ApplicationController::class, 'uploadDocuments'])->name('applications.documents.upload');
    Route::delete('/applications/{application}/documents/{document}', [ApplicationController::class, 'deleteDocument'])->name('applications.documents.delete');
    Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');
});

// Registered after the /loans/{loanProduct:slug}/apply route above (and after loans.show)
// so that literal path always wins the match first — this wildcard second segment can
// never shadow it, since Laravel tries routes in registration order.
Route::get('/loans/{loanProduct:slug}/{landingPage:slug}', [LoanLandingPageController::class, 'show'])->name('loans.landing-pages.show');

Route::get('/calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('/calculators/emi/{category}', [CalculatorController::class, 'emi'])->name('calculators.emi');
Route::get('/calculators/fixed-deposit', [CalculatorController::class, 'fixedDeposit'])->name('calculators.fixed-deposit');
Route::get('/calculators/sip', [CalculatorController::class, 'sip'])->name('calculators.sip');
Route::get('/calculators/daily-sip', [CalculatorController::class, 'dailySip'])->name('calculators.daily-sip');
Route::get('/calculators/gst', [CalculatorController::class, 'gst'])->name('calculators.gst');
Route::get('/calculators/eligibility/{category}', [CalculatorController::class, 'eligibility'])->name('calculators.eligibility');
Route::get('/calculators/prepayment/{category}', [CalculatorController::class, 'prepayment'])->name('calculators.prepayment');

Route::get('/resources', [ArticleController::class, 'index'])->name('resources.index');
Route::get('/resources/{article:slug}', [ArticleController::class, 'show'])->name('resources.show');

Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:contact-form');
