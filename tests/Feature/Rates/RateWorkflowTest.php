<?php

use App\Enums\LoanCategory;
use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Rates\RateRoles;
use App\Support\Rates\RateWorkflow;
use App\Support\Rates\RateWorkflowException;
use Illuminate\Auth\Access\AuthorizationException;

function rateUser(string $role): User
{
    return tap(User::factory()->create(['is_admin' => true]))->syncRoles([$role]);
}

function personalLoanOffer(): LenderProduct
{
    return LenderProduct::factory()
        ->for(LoanProduct::factory()->published()->state(['category' => LoanCategory::PersonalLoan]), 'loanProduct')
        ->create(['interest_rate_from' => 12.0, 'interest_rate_to' => 20.0]);
}

beforeEach(function () {
    $this->editor = rateUser(RateRoles::EDITOR_ROLE);
    $this->publisher = rateUser(RateRoles::PUBLISHER_ROLE);
});

it('lets an editor submit a verified draft for approval and records who submitted it', function () {
    $draft = LenderProductRate::factory()->for(personalLoanOffer(), 'lenderProduct')->create();

    RateWorkflow::submit($draft, $this->editor);

    expect($draft->fresh())
        ->status->toBe(RateStatus::PendingApproval)
        ->submitted_by->toBe($this->editor->id)
        ->submitted_at->not->toBeNull();
});

it('refuses to submit a rate without verified, current, official evidence', function (array $state, string $problem) {
    $draft = LenderProductRate::factory()->for(personalLoanOffer(), 'lenderProduct')->create($state);

    expect(fn () => RateWorkflow::submit($draft, $this->editor))
        ->toThrow(RateWorkflowException::class, $problem);

    expect($draft->fresh()->status)->toBe(RateStatus::Draft);
})->with([
    'not marked verified' => [['verification_status' => RateVerificationStatus::Unverified], 'Mark the rate as verified'],
    'no source link' => [['source_url' => null], 'Add the https:// link'],
    'plain http source' => [['source_url' => 'http://lender.example/rates'], 'Add the https:// link'],
    'never checked' => [['last_verified_at' => null], 'Enter the date you last checked'],
    'checked too long ago' => [['last_verified_at' => now()->subDays(LenderProductRate::REVERIFY_AFTER_DAYS + 2)->toDateString()], 'last verified more than 90 days ago'],
    'third-party site not named' => [['source_type' => RateSourceType::ThirdParty, 'source_name' => null], 'Name the third-party site'],
]);

it('refuses impossible rate figures', function (array $state, string $problem) {
    $draft = LenderProductRate::factory()->for(personalLoanOffer(), 'lenderProduct')->create($state);

    expect(fn () => RateWorkflow::publish($draft, $this->publisher))
        ->toThrow(RateWorkflowException::class, $problem);
})->with([
    'zero starting rate' => [['rate_min' => 0], 'The starting rate must be above 0'],
    'typing error above the cap' => [['rate_min' => 99.5, 'rate_max' => null], 'The starting rate must be above 0 and at most 60.00% p.a.'],
    'maximum below the starting rate' => [['rate_min' => 12, 'rate_max' => 10], 'The maximum rate must be at least the starting rate'],
    'loan quoted per month' => [['rate_unit' => RateUnit::PerMonth, 'rate_min' => 1.5, 'rate_max' => 2.0], 'Personal Loan rates are published p.a.'],
    'promotion without an end' => [['promo_rate' => 9.5, 'promo_label' => 'Festive', 'promo_ends_at' => null], 'A promotional rate needs an end date'],
    'promotion already over' => [['promo_rate' => 9.5, 'promo_label' => 'Festive', 'promo_ends_at' => now()->subHour()], 'The promotion has already ended'],
    'validity already over' => [['effective_until' => now()->subDays(2)->toDateString()], 'has passed'],
]);

it('stops an editor from publishing and lets a publisher approve the submitted rate', function () {
    $draft = LenderProductRate::factory()->for(personalLoanOffer(), 'lenderProduct')->create();
    RateWorkflow::submit($draft, $this->editor);

    expect(fn () => RateWorkflow::publish($draft, $this->editor))->toThrow(AuthorizationException::class);

    RateWorkflow::publish($draft, $this->publisher);

    expect($draft->fresh())
        ->status->toBe(RateStatus::Published)
        ->published_by->toBe($this->publisher->id)
        ->submitted_by->toBe($this->editor->id);
});

it('archives the live version when a new version of the same rate is published, keeping both', function () {
    $offer = personalLoanOffer();
    $live = LenderProductRate::factory()->published()->for($offer, 'lenderProduct')->create(['rate_min' => 10.5, 'rate_max' => 18]);

    $next = RateWorkflow::draftFrom($live, $this->editor);
    $next->update(['rate_min' => 10.75]);
    RateWorkflow::publish($next, $this->publisher);

    expect($live->fresh())->status->toBe(RateStatus::Archived)->archived_at->not->toBeNull()
        ->and($next->fresh())->status->toBe(RateStatus::Published)->version->toBe(2)->series_id->toBe($live->series_id)
        ->and($offer->fresh())->interest_rate_from->toBe('10.75')->interest_rate_to->toBe('18.00');
});

it('restores an earlier version as a new draft that must be approved again', function () {
    $offer = personalLoanOffer();
    $first = LenderProductRate::factory()->published()->for($offer, 'lenderProduct')->create(['rate_min' => 9.99]);
    $second = RateWorkflow::draftFrom($first, $this->editor);
    $second->update(['rate_min' => 11.49]);
    RateWorkflow::publish($second, $this->publisher);

    $restored = RateWorkflow::draftFrom($first->fresh(), $this->editor);

    expect($restored)
        ->status->toBe(RateStatus::Draft)
        ->version->toBe(3)
        ->rate_min->toBe('9.99')
        ->and($second->fresh()->status)->toBe(RateStatus::Published);
});

it('refuses a second draft while one is already open in the same series', function () {
    $live = LenderProductRate::factory()->published()->for(personalLoanOffer(), 'lenderProduct')->create();
    RateWorkflow::draftFrom($live, $this->editor);

    expect(fn () => RateWorkflow::draftFrom($live, $this->editor))
        ->toThrow(RateWorkflowException::class, 'is already Draft');
});

it('sends a submitted rate back to draft with the reviewer\'s reason', function () {
    $draft = LenderProductRate::factory()->pendingApproval()->for(personalLoanOffer(), 'lenderProduct')->create();

    RateWorkflow::reject($draft, $this->publisher, 'Source page shows 10.99%, not 10.49%.');

    expect($draft->fresh())
        ->status->toBe(RateStatus::Draft)
        ->review_note->toBe('Source page shows 10.99%, not 10.49%.');
});

it('withdraws a live rate without a replacement', function () {
    $live = LenderProductRate::factory()->published()->for(personalLoanOffer(), 'lenderProduct')->create();

    RateWorkflow::withdraw($live, $this->publisher);

    expect($live->fresh()->status)->toBe(RateStatus::Archived);
});

it('keeps submitted and published versions immutable and undeletable', function () {
    $live = LenderProductRate::factory()->published()->for(personalLoanOffer(), 'lenderProduct')->create(['rate_min' => 10.5]);

    expect(fn () => $live->update(['rate_min' => 8.5]))->toThrow(LogicException::class, 'create a new version instead')
        ->and(fn () => $live->delete())->toThrow(LogicException::class, 'kept as history')
        ->and($live->fresh()->rate_min)->toBe('10.50');
});

it('does not let a read-only reviewer create or submit rates', function () {
    $reviewer = rateUser(RateRoles::REVIEWER_ROLE);
    $draft = LenderProductRate::factory()->for(personalLoanOffer(), 'lenderProduct')->create();

    expect(fn () => RateWorkflow::submit($draft, $reviewer))->toThrow(AuthorizationException::class)
        ->and(fn () => RateWorkflow::draftFrom($draft, $reviewer))->toThrow(AuthorizationException::class)
        ->and($reviewer->can('viewAny', LenderProductRate::class))->toBeTrue();
});
