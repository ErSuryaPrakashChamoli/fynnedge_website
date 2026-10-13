<?php

use App\Enums\LoanCategory;
use App\Enums\RateStatus;
use App\Enums\RateVerificationStatus;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Support\Rates\RateAuditImport;
use App\Support\Rates\RateBook;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function auditRecord(string $lender, array $overrides = []): array
{
    return [
        'lender' => $lender, 'product_slug' => 'personal-loan', 'variant' => null, 'primary' => true,
        'status' => 'VERIFIED', 'rate_min' => 10.5, 'rate_max' => 20.0, 'rate_unit' => 'per_annum',
        'rate_type' => 'fixed', 'repayment_method' => 'reducing_balance', 'benchmark' => null,
        'borrower_segment' => 'Salaried and self-employed', 'conditions' => null, 'processing_fee' => 'Up to 2%',
        'source_url' => 'https://www.lender.example/personal-loan', 'source_title' => 'Personal loan rates',
        'evidence' => 'Interest rate 10.50% to 20.00% p.a.', 'page_effective_date' => null, 'offer_expiry' => null, 'notes' => null,
        ...$overrides,
    ];
}

function auditDataset(array $records): string
{
    $path = tempnam(sys_get_temp_dir(), 'rate-audit');
    file_put_contents($path, json_encode(['researched_on' => '2026-10-13', 'records' => $records]));

    return $path;
}

function auditOffer(LoanProduct $product, string $lenderName): LenderProduct
{
    return LenderProduct::factory()->for($product, 'loanProduct')->for(Lender::factory()->state(['name' => $lenderName]))->create([
        'interest_rate_from' => 12.0,
        'interest_rate_to' => 18.0,
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-10-13 10:00:00');
    $this->product = LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan, 'slug' => 'personal-loan']);
});

it('queues verified primary rates for approval with their evidence and publishes nothing', function () {
    $offer = auditOffer($this->product, 'Evidence Bank');

    RateAuditImport::run(auditDataset([auditRecord('Evidence Bank')]));

    $rate = LenderProductRate::query()->sole();
    expect($rate)
        ->lender_product_id->toBe($offer->id)
        ->status->toBe(RateStatus::PendingApproval)
        ->verification_status->toBe(RateVerificationStatus::Verified)
        ->borrower_segment->toBeNull()
        ->rate_min->toBe('10.50')
        ->source_url->toBe('https://www.lender.example/personal-loan')
        ->last_verified_at->toDateString()->toBe('2026-10-13')
        ->and($rate->source_notes)->toContain('Interest rate 10.50% to 20.00% p.a.')->toContain('Before the audit the website showed 12.00%–18.00%.')
        ->and(RateBook::headlineFor($this->product))->toBeNull();
});

it('keeps segment variants, snippet-only figures and unconfirmed old figures as drafts', function () {
    auditOffer($this->product, 'Variant Bank');
    auditOffer($this->product, 'Snippet Bank');
    auditOffer($this->product, 'Silent Bank');

    $result = RateAuditImport::run(auditDataset([
        auditRecord('Variant Bank'),
        auditRecord('Variant Bank', ['primary' => false, 'variant' => 'Her Loan', 'borrower_segment' => 'Women borrowers', 'rate_min' => 10.25]),
        auditRecord('Snippet Bank', ['status' => 'SNIPPET_ONLY', 'rate_min' => 11.0]),
        auditRecord('Silent Bank', ['status' => 'UNVERIFIED', 'rate_min' => null, 'rate_max' => null, 'notes' => 'No rate published']),
    ]));

    expect($result)->toMatchArray(['awaiting_approval' => 1, 'verified_drafts' => 1, 'unverified_drafts' => 1, 'legacy_drafts' => 1])
        ->and(LenderProductRate::query()->where('borrower_segment', 'Women borrowers')->sole())
        ->status->toBe(RateStatus::Draft)->verification_status->toBe(RateVerificationStatus::Verified)
        ->and(LenderProductRate::query()->where('rate_min', 11.0)->sole())
        ->verification_status->toBe(RateVerificationStatus::Unverified)
        ->and(LenderProductRate::query()->where('rate_min', 12.0)->sole()->source_notes)
        ->toContain('Pre-audit figure')->toContain('No rate published');
});

it('keeps a policy band or a disclosure of rates already charged out of the approval queue', function () {
    auditOffer($this->product, 'Disclosure Bank');

    RateAuditImport::run(auditDataset([auditRecord('Disclosure Bank', ['variant' => 'Personal Loan — Apr-Jun 2026 disclosed range'])]));

    expect(LenderProductRate::query()->sole())
        ->status->toBe(RateStatus::Draft)
        ->verification_status->toBe(RateVerificationStatus::Verified)
        ->borrower_segment->toBeNull()
        ->source_notes->toContain('not an advertised starting rate');
});

it('lists products a lender does not offer and gives them no rate', function () {
    auditOffer($this->product, 'Former Bank');

    $result = RateAuditImport::run(auditDataset([auditRecord('Former Bank', ['status' => 'NOT_OFFERED', 'rate_min' => null, 'rate_max' => null])]));

    expect($result['not_offered'])->toBe(['Former Bank — '.$this->product->name])
        ->and(LenderProductRate::query()->count())->toBe(0);
});

it('changes nothing when run a second time', function () {
    auditOffer($this->product, 'Twice Bank');
    $dataset = auditDataset([auditRecord('Twice Bank')]);

    RateAuditImport::run($dataset);
    RateAuditImport::run($dataset);

    expect(LenderProductRate::query()->count())->toBe(1);
});

it('replaces the seeded typed rate sentence in existing landing pages with the live token', function () {
    $page = LoanLandingPage::factory()->for($this->product, 'loanProduct')->create([
        'body' => '<p>Personal Loan is typically available from ₹25,000 to ₹5,000,000 over a tenure of 12–84 months, at indicative rates of 10.49%–24% p.a.</p><p>Rates from 9% elsewhere stay.</p>',
    ]);

    (require database_path('migrations/2026_10_13_122725_replace_typed_rates_in_loan_landing_page_bodies.php'))->up();

    expect($page->fresh()->body)->toBe('<p>Personal Loan is typically available from ₹25,000 to ₹5,000,000 over a tenure of 12–84 months, [rate-range:personal-loan].</p><p>Rates from 9% elsewhere stay.</p>');
});

it('reads the shipped October 2026 audit dataset', function () {
    $dataset = json_decode(file_get_contents(base_path(RateAuditImport::DATASET)), true);

    expect($dataset['researched_on'])->toBe('2026-10-13')
        ->and($dataset['records'])->not->toBeEmpty()
        ->and(collect($dataset['records'])->pluck('status')->unique()->diff(['VERIFIED', 'SNIPPET_ONLY', 'UNVERIFIED', 'NOT_OFFERED']))->toBeEmpty();
});
