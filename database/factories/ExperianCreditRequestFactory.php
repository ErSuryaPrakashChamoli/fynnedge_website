<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianFingerprint;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fabricated test data only — never a real PAN, person or bureau response.
 *
 * @extends Factory<ExperianCreditRequest>
 */
class ExperianCreditRequestFactory extends Factory
{
    protected $model = ExperianCreditRequest::class;

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
            'product' => ExperianProduct::Report,
            'request_type' => ExperianRequestType::Standard,
            'channel' => 'admin',
            'status' => ExperianRequestStatus::Pending,
            'pan_hash' => $panHash,
            'in_flight_key' => ExperianFingerprint::inFlight($panHash, ExperianProduct::Report->value),
            'request_fingerprint' => ExperianFingerprint::request($pan, $name, $mobile),
            'pan_masked' => PanNumber::mask($pan),
            'mobile_masked' => MobileNumber::mask($mobile),
            'pan_encrypted' => $pan,
            'full_name' => $name,
            'mobile' => $mobile,
            'provider' => 'surepass',
            'requested_at' => now(),
        ];
    }

    public function score(): static
    {
        return $this->state(fn (array $attributes) => [
            'product' => ExperianProduct::Score,
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : ExperianFingerprint::inFlight($attributes['pan_hash'], ExperianProduct::Score->value),
        ]);
    }

    /**
     * A PDF request; with successful(), a stored (fake, encrypted) file is not
     * created — set pdf_path yourself when a test needs one.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'product' => ExperianProduct::Pdf,
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : ExperianFingerprint::inFlight($attributes['pan_hash'], ExperianProduct::Pdf->value),
        ]);
    }

    public function forPan(string $pan): static
    {
        return $this->state(function (array $attributes) use ($pan) {
            $panHash = PanNumber::hash($pan);
            $product = $attributes['product'] instanceof ExperianProduct ? $attributes['product']->value : $attributes['product'];

            return [
                'pan_hash' => $panHash,
                'in_flight_key' => $attributes['in_flight_key'] === null ? null : ExperianFingerprint::inFlight($panHash, $product),
                'request_fingerprint' => ExperianFingerprint::request($pan, $attributes['full_name'], $attributes['mobile']),
                'pan_masked' => PanNumber::mask($pan),
                'pan_encrypted' => $pan,
            ];
        });
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => ExperianRequestStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse  A full provider body; a minimal one when null.
     */
    public function successful(?array $rawResponse = null, int $score = 761): static
    {
        return $this->state(fn () => [
            'status' => ExperianRequestStatus::Success,
            'in_flight_key' => null,
            'score' => $score,
            'http_status' => 200,
            'provider_client_id' => 'credit_report_experian_test_'.Str::random(8),
            'raw_response' => $rawResponse ?? [
                'success' => true,
                'status_code' => 200,
                'message_code' => 'success',
                'data' => ['client_id' => 'credit_report_experian_TEST', 'credit_score' => (string) $score, 'credit_report' => ['CreditProfileHeader' => ['ReportNumber' => 'TEST-REPORT']]],
            ],
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function failed(ExperianErrorCode $code = ExperianErrorCode::ProviderUnavailable): static
    {
        return $this->state(fn () => [
            'status' => ExperianRequestStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function awaitingReconciliation(ExperianErrorCode $code = ExperianErrorCode::Timeout): static
    {
        return $this->state(fn () => [
            'status' => ExperianRequestStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
