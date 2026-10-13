<?php

use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Services\MobileNumber;

it('redeems a verified challenge exactly once', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->create(['mobile_number' => '9876543210']);
    $sameRowElsewhere = MobileOtpChallenge::query()->findOrFail($challenge->id);

    $first = $challenge->consumeFor('9876543210', MobileOtpPurpose::LoanEnquiry);
    $second = $sameRowElsewhere->consumeFor('9876543210', MobileOtpPurpose::LoanEnquiry);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($challenge->fresh()->consumed_at)->not->toBeNull();
});

it('refuses to redeem a challenge that is not a verified match', function (Closure $makeChallenge, string $mobile, MobileOtpPurpose $purpose) {
    $challenge = $makeChallenge();

    expect($challenge->consumeFor($mobile, $purpose))->toBeFalse()
        ->and($challenge->fresh()->consumed_at)->toBeNull();
})->with([
    'never verified' => [fn () => MobileOtpChallenge::factory()->create(['mobile_number' => '9876543210']), '9876543210', MobileOtpPurpose::LoanEnquiry],
    'another number' => [fn () => MobileOtpChallenge::factory()->verified()->create(['mobile_number' => '9876543210']), '9123456780', MobileOtpPurpose::LoanEnquiry],
    'another purpose' => [fn () => MobileOtpChallenge::factory()->verified()->create(['mobile_number' => '9876543210']), '9876543210', MobileOtpPurpose::LoanApplication],
    'verified too long ago' => [fn () => MobileOtpChallenge::factory()->verified()->create(['mobile_number' => '9876543210', 'verified_at' => now()->subMinutes(31)]), '9876543210', MobileOtpPurpose::LoanEnquiry],
]);

it('reports a pending challenge past its expiry as expired, in the accessor and the query scope alike', function () {
    $live = MobileOtpChallenge::factory()->create();
    $lapsed = MobileOtpChallenge::factory()->expired()->create();
    $verified = MobileOtpChallenge::factory()->verified()->expired()->create();

    expect($lapsed->effectiveStatus())->toBe(MobileOtpStatus::Expired)
        ->and($live->effectiveStatus())->toBe(MobileOtpStatus::Pending)
        ->and($verified->effectiveStatus())->toBe(MobileOtpStatus::Verified)
        ->and(MobileOtpChallenge::query()->whereEffectiveStatus(MobileOtpStatus::Expired)->pluck('id')->all())->toBe([$lapsed->id])
        ->and(MobileOtpChallenge::query()->whereEffectiveStatus(MobileOtpStatus::Pending)->pluck('id')->all())->toBe([$live->id]);
});

it('normalises the ways visitors type an indian mobile number', function (mixed $typed, string $stored) {
    expect(MobileNumber::normalize($typed))->toBe($stored);
})->with([
    ['+91 98765-43210', '9876543210'],
    ['919876543210', '9876543210'],
    ['09876543210', '9876543210'],
    ['(98765) 43210', '9876543210'],
    [['9876543210'], ''],
]);

it('masks all but the last four digits', function () {
    expect(MobileNumber::mask('8800618269'))->toBe('******8269');
});
