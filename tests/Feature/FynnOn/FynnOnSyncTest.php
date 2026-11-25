<?php

use App\Enums\EnquiryStatus;
use App\Filament\Pages\FynnOnSync as FynnOnSyncPage;
use App\Models\AuditLog;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Jobs\SendLeadToFynnOn;
use App\Modules\FynnOn\Notifications\FynnOnDeliveryFailed;
use App\Modules\FynnOn\Services\FynnOnClient;
use App\Modules\FynnOn\Services\FynnOnSync;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function connectFynnOn(array $overrides = []): void
{
    config(['services.fynnon' => [
        'url' => 'https://lms.fynnon.test/api/leads',
        'token' => 'test-token',
        'signing_secret' => 'shh',
        'timeout' => 5,
        'sources' => 'enquiry,credit_score_check,online_application,loan_application,otp_verification',
        'alert_email' => 'ops@fynnedge.test',
        ...$overrides,
    ]]);
}

function sendJob(LeadSource $source, int $id, int $attempts = 1, bool $force = false): SendLeadToFynnOn
{
    $job = (new SendLeadToFynnOn($source->value, $id, force: $force))->withFakeQueueInteractions();
    $job->job->attempts = $attempts;

    return $job;
}

it('sends nothing while the LMS is not configured', function () {
    Queue::fake();

    ContactEnquiry::factory()->create();

    Queue::assertNothingPushed();
});

it('queues every new enquiry for the LMS the moment it is saved', function () {
    connectFynnOn();
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();

    Queue::assertPushed(SendLeadToFynnOn::class, fn (SendLeadToFynnOn $job): bool => $job->source === 'enquiry' && $job->leadId === $enquiry->id);
});

it('delivers the lead with auth, idempotency key and signature, and records the confirmed result', function () {
    connectFynnOn();
    Http::fake(['lms.fynnon.test/*' => Http::response(['reference' => 'LMS-42'], 201)]);
    Queue::fake();

    $product = LoanProduct::factory()->create(['name' => 'Personal Loan']);
    $enquiry = ContactEnquiry::factory()->forLoanProduct($product)->create(['phone' => '9876543210', 'phone_verified_at' => now()]);

    sendJob(LeadSource::Enquiry, $enquiry->id)->handle(app(FynnOnClient::class));

    Http::assertSent(function (Request $request) use ($enquiry): bool {
        $body = json_decode($request->body(), true);
        $signature = 'sha256='.hash_hmac('sha256', $request->header('X-FynnEdge-Timestamp')[0].'.'.$request->body(), 'shh');

        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->header('Idempotency-Key')[0] === "fynnedge:enquiry:{$enquiry->public_id}:lead.created"
            && $request->header('X-FynnEdge-Signature')[0] === $signature
            && $body['reference'] === "fynnedge:enquiry:{$enquiry->public_id}"
            && $body['contact']['mobile'] === '9876543210'
            && $body['contact']['mobile_verified'] === true
            && $body['loan']['product'] === 'Personal Loan'
            && $body['attribution']['page_path'] === '/loans/'.$enquiry->loanProduct->slug;
    });

    $log = AuditLog::query()->where('auditable_id', $enquiry->id)->where('auditable_type', $enquiry->getMorphClass())->sole();

    expect($log->action)->toBe(FynnOnSyncStatus::Sent->value)
        ->and($log->user_id)->toBeNull()
        ->and($log->changes)->toMatchArray(['lms_reference' => 'LMS-42', 'http_status' => 201, 'attempt' => 1])
        ->and($log->changes)->not->toHaveKey('contact')
        ->and(FynnOnSync::status($enquiry))->toBe(FynnOnSyncStatus::Sent);
});

it('never sends PAN, date of birth, score or report data', function () {
    connectFynnOn();
    Http::fake(['*' => Http::response(['success' => true])]);
    Queue::fake();

    $check = CreditScoreCheck::factory()->create(['pan_number' => 'ABCDE1234F', 'score' => 812]);

    sendJob(LeadSource::CreditScoreCheck, $check->id)->handle(app(FynnOnClient::class));

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'ABCDE1234F')
        && ! str_contains($request->body(), '812')
        && ! str_contains($request->body(), 'date_of_birth')
        && json_decode($request->body(), true)['attribution']['page_url'] === null);
});

it('does not count a 2xx reply without an acknowledgement as delivered', function () {
    connectFynnOn();
    Http::fake(['*' => Http::response('<html>OK</html>', 200)]);
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();
    sendJob(LeadSource::Enquiry, $enquiry->id)->handle(app(FynnOnClient::class));

    expect(FynnOnSync::status($enquiry))->toBe(FynnOnSyncStatus::Abandoned);
});

it('treats a 409 as already in the LMS', function () {
    connectFynnOn();
    Http::fake(['*' => Http::response(['reference' => 'LMS-1'], 409)]);
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();
    sendJob(LeadSource::Enquiry, $enquiry->id)->handle(app(FynnOnClient::class));

    expect(FynnOnSync::status($enquiry))->toBe(FynnOnSyncStatus::Duplicate)
        ->and(FynnOnSync::status($enquiry)->isDelivered())->toBeTrue();
});

it('retries a temporary failure with a growing delay', function () {
    connectFynnOn();
    Http::fake(['*' => Http::response(['message' => 'busy'], 503)]);
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();
    $job = sendJob(LeadSource::Enquiry, $enquiry->id, attempts: 2);
    $job->handle(app(FynnOnClient::class));

    $job->assertReleased(delay: SendLeadToFynnOn::RETRY_DELAYS[1]);

    expect(FynnOnSync::status($enquiry))->toBe(FynnOnSyncStatus::Failed)
        ->and(FynnOnSync::latest($enquiry)->changes['error'])->toContain('503');
});

it('gives up after the last attempt, or at once on an unfixable error, and alerts', function (int $status, int $attempt) {
    connectFynnOn();
    Notification::fake();
    Http::fake(['*' => Http::response(['message' => 'nope'], $status)]);
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();
    $job = sendJob(LeadSource::Enquiry, $enquiry->id, attempts: $attempt);
    $job->handle(app(FynnOnClient::class));

    $job->assertNotReleased();

    expect(FynnOnSync::status($enquiry))->toBe(FynnOnSyncStatus::Abandoned);
    Notification::assertSentOnDemand(FynnOnDeliveryFailed::class);
})->with([
    'server error on the 5th attempt' => [500, 5],
    'validation error on the 1st attempt' => [422, 1],
]);

it('does not send the same event twice unless an admin forces a resend', function () {
    connectFynnOn();
    Http::fake(['*' => Http::response(['reference' => 'LMS-7'], 201)]);
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create();
    $client = app(FynnOnClient::class);

    sendJob(LeadSource::Enquiry, $enquiry->id)->handle($client);
    sendJob(LeadSource::Enquiry, $enquiry->id)->handle($client);
    Http::assertSentCount(1);

    sendJob(LeadSource::Enquiry, $enquiry->id, force: true)->handle($client);
    Http::assertSentCount(2);
});

it('queues only records that have become leads', function () {
    connectFynnOn();
    Queue::fake();

    $session = JourneySession::factory()->create(['phone_number' => '9876500000']);
    MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::CustomerLogin)->create();
    $pendingOtp = MobileOtpChallenge::factory()->create();

    Queue::assertNothingPushed();

    $session->update(['phone_verified_at' => now()]);
    $pendingOtp->update(['status' => MobileOtpStatus::Verified, 'verified_at' => now()]);

    Queue::assertPushed(SendLeadToFynnOn::class, 2);
});

it('sends a reopened enquiry again as lead.reopened', function () {
    connectFynnOn();
    Queue::fake();

    $enquiry = ContactEnquiry::factory()->create(['status' => EnquiryStatus::Closed]);
    $enquiry->update(['status' => EnquiryStatus::New]);

    Queue::assertPushed(SendLeadToFynnOn::class, fn (SendLeadToFynnOn $job): bool => $job->event === FynnOnSync::EVENT_REOPENED);
});

it('lets lead staff see delivery status and blocks everyone else', function () {
    $this->seed(RoleSeeder::class);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(FynnOnSyncPage::getUrl())
        ->assertOk()
        ->assertSeeText('Not connected');

    $marketer = User::factory()->create(['is_admin' => true]);
    $marketer->syncRoles(['Marketing']);

    $this->actingAs($marketer)->get(FynnOnSyncPage::getUrl())->assertForbidden();
});

it('queues the backlog only when asked for a range', function () {
    connectFynnOn();
    Queue::fake();
    Queue::assertNothingPushed();

    ContactEnquiry::withoutEvents(fn () => ContactEnquiry::factory()->count(2)->create(['created_at' => now()->subDays(3)]));

    expect(Artisan::call('fynnon:send-pending'))->toBe(1);
    Queue::assertNothingPushed();

    Artisan::call('fynnon:send-pending', ['--since' => now()->subDays(10)->toDateString()]);

    expect(Artisan::output())->toContain('2 lead(s) queued');
    Queue::assertPushed(SendLeadToFynnOn::class, 2);
});
