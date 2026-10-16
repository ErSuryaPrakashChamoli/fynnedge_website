<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Enums\Gender;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilFingerprint;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fabricated test data only — never a real PAN, person or bureau response.
 *
 * @extends Factory<CibilCreditRequest>
 */
class CibilCreditRequestFactory extends Factory
{
    protected $model = CibilCreditRequest::class;

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
            'product' => CibilProduct::Report,
            'request_type' => CibilRequestType::Standard,
            'channel' => 'admin',
            'status' => CibilRequestStatus::Pending,
            'pan_hash' => $panHash,
            'in_flight_key' => CibilFingerprint::inFlight($panHash, CibilProduct::Report->value),
            'request_fingerprint' => CibilFingerprint::request($pan, $name, $mobile, Gender::Male->value),
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
            'product' => CibilProduct::Score,
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : CibilFingerprint::inFlight($attributes['pan_hash'], CibilProduct::Score->value),
        ]);
    }

    /**
     * A PDF request; with successful(), a stored (fake, encrypted) file is not
     * created — set pdf_path yourself when a test needs one.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'product' => CibilProduct::Pdf,
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : CibilFingerprint::inFlight($attributes['pan_hash'], CibilProduct::Pdf->value),
        ]);
    }

    public function forPan(string $pan): static
    {
        return $this->state(function (array $attributes) use ($pan) {
            $panHash = PanNumber::hash($pan);
            $product = $attributes['product'] instanceof CibilProduct ? $attributes['product']->value : $attributes['product'];

            return [
                'pan_hash' => $panHash,
                'in_flight_key' => $attributes['in_flight_key'] === null ? null : CibilFingerprint::inFlight($panHash, $product),
                'request_fingerprint' => CibilFingerprint::request($pan, $attributes['full_name'], $attributes['mobile'], $attributes['gender'] instanceof Gender ? $attributes['gender']->value : (string) $attributes['gender']),
                'pan_masked' => PanNumber::mask($pan),
                'pan_encrypted' => $pan,
            ];
        });
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => CibilRequestStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse  A full provider body; a minimal one when null.
     */
    public function successful(?array $rawResponse = null, int $score = 761): static
    {
        return $this->state(fn () => [
            'status' => CibilRequestStatus::Success,
            'in_flight_key' => null,
            'score' => $score,
            'http_status' => 200,
            'provider_client_id' => 'credit_report_cibil_test_'.Str::random(8),
            'raw_response' => $rawResponse ?? [
                'success' => true,
                'status_code' => 200,
                'message_code' => 'success',
                'data' => ['client_id' => 'credit_report_cibil_TEST', 'credit_score' => (string) $score, 'credit_report' => [['control_number' => 'TEST-CONTROL', 'accounts' => []]]],
            ],
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function failed(CibilErrorCode $code = CibilErrorCode::ProviderUnavailable): static
    {
        return $this->state(fn () => [
            'status' => CibilRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function awaitingReconciliation(CibilErrorCode $code = CibilErrorCode::Timeout): static
    {
        return $this->state(fn () => [
            'status' => CibilRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
