<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxFingerprint;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fabricated test data only — never a real PAN, person or bureau response.
 *
 * @extends Factory<EquifaxCreditRequest>
 */
class EquifaxCreditRequestFactory extends Factory
{
    protected $model = EquifaxCreditRequest::class;

    public function definition(): array
    {
        $pan = strtoupper($this->faker->bothify('?????####?'));
        $name = $this->faker->firstName().' '.$this->faker->lastName();
        $mobile = '9'.$this->faker->numerify('#########');
        $panHash = PanNumber::hash($pan);

        return [
            'public_id' => Str::uuid(),
            'customer_id' => Customer::factory(),
            'requested_by' => User::factory(),
            'product' => EquifaxProduct::Report,
            'request_type' => EquifaxRequestType::Standard,
            'channel' => 'admin',
            'status' => EquifaxRequestStatus::Pending,
            'pan_hash' => $panHash,
            'in_flight_key' => EquifaxFingerprint::inFlight($panHash, EquifaxProduct::Report->value),
            'request_fingerprint' => EquifaxFingerprint::request($pan, $name, $mobile, Gender::Male->value),
            'pan_masked' => PanNumber::mask($pan),
            'mobile_masked' => MobileNumber::mask($mobile),
            'pan_encrypted' => $pan,
            'full_name' => $name,
            'mobile' => $mobile,
            'gender' => Gender::Male,
            'provider' => 'surepass',
            'requested_at' => now(),
        ];
    }

    public function score(): static
    {
        return $this->state(fn (array $attributes) => [
            'product' => EquifaxProduct::Score,
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : EquifaxFingerprint::inFlight($attributes['pan_hash'], EquifaxProduct::Score->value),
        ]);
    }

    public function forPan(string $pan): static
    {
        return $this->state(function (array $attributes) use ($pan) {
            $panHash = PanNumber::hash($pan);
            $product = $attributes['product'] instanceof EquifaxProduct ? $attributes['product']->value : $attributes['product'];

            return [
                'pan_hash' => $panHash,
                'in_flight_key' => $attributes['in_flight_key'] === null ? null : EquifaxFingerprint::inFlight($panHash, $product),
                'request_fingerprint' => EquifaxFingerprint::request($pan, $attributes['full_name'], $attributes['mobile'], $attributes['gender'] instanceof Gender ? $attributes['gender']->value : $attributes['gender']),
                'pan_masked' => PanNumber::mask($pan),
                'pan_encrypted' => $pan,
            ];
        });
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => EquifaxRequestStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse  A full provider body; a minimal one when null.
     */
    public function successful(?array $rawResponse = null, int $score = 761): static
    {
        return $this->state(fn () => [
            'status' => EquifaxRequestStatus::Success,
            'in_flight_key' => null,
            'score' => $score,
            'http_status' => 200,
            'provider_client_id' => 'credit_report_v2_test_'.Str::random(8),
            'raw_response' => $rawResponse ?? [
                'success' => true,
                'status_code' => 200,
                'message_code' => 'success',
                'data' => ['credit_score' => (string) $score, 'credit_report' => [['InquiryResponseHeader' => ['ReportOrderNO' => 'TEST-ORDER']]]],
            ],
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function failed(EquifaxErrorCode $code = EquifaxErrorCode::ProviderUnavailable): static
    {
        return $this->state(fn () => [
            'status' => EquifaxRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function awaitingReconciliation(EquifaxErrorCode $code = EquifaxErrorCode::Timeout): static
    {
        return $this->state(fn () => [
            'status' => EquifaxRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
