<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRequestFingerprint;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fabricated test data only — never a real PAN, person or bureau response.
 *
 * @extends Factory<CrifReport>
 */
class CrifReportFactory extends Factory
{
    protected $model = CrifReport::class;

    public function definition(): array
    {
        $pan = strtoupper($this->faker->bothify('?????####?'));
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $mobile = '9'.$this->faker->numerify('#########');

        return [
            'public_id' => Str::uuid(),
            'customer_id' => Customer::factory(),
            'requested_by' => User::factory(),
            'request_type' => CrifRequestType::Standard,
            'status' => CrifReportStatus::Pending,
            'pan_hash' => PanNumber::hash($pan),
            'in_flight_key' => PanNumber::hash($pan),
            'request_fingerprint' => CrifRequestFingerprint::make($pan, $firstName, $lastName, $mobile),
            'pan_masked' => PanNumber::mask($pan),
            'mobile_masked' => MobileNumber::mask($mobile),
            'pan_encrypted' => $pan,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'mobile' => $mobile,
            'provider' => 'surepass',
            'requested_at' => now(),
        ];
    }

    public function forPan(string $pan): static
    {
        return $this->state(fn (array $attributes) => [
            'pan_hash' => PanNumber::hash($pan),
            'in_flight_key' => $attributes['in_flight_key'] === null ? null : PanNumber::hash($pan),
            'request_fingerprint' => CrifRequestFingerprint::make($pan, $attributes['first_name'], $attributes['last_name'], $attributes['mobile']),
            'pan_masked' => PanNumber::mask($pan),
            'pan_encrypted' => $pan,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => CrifReportStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse  A full provider body; a minimal one when null.
     */
    public function successful(?array $rawResponse = null, int $score = 742): static
    {
        return $this->state(fn () => [
            'status' => CrifReportStatus::Success,
            'in_flight_key' => null,
            'score' => $score,
            'http_status' => 200,
            'provider_client_id' => 'credit_report_crif_test_'.Str::random(8),
            'provider_report_id' => 'TEST-'.$this->faker->numerify('########'),
            'raw_response' => $rawResponse ?? [
                'success' => true,
                'status_code' => 200,
                'message_code' => 'success',
                'data' => [
                    'credit_score' => (string) $score,
                    'credit_report' => ['HEADER' => ['REPORT-ID' => 'TEST-REPORT', 'STATUS' => 'SUCCESS']],
                ],
            ],
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function failed(CrifErrorCode $code = CrifErrorCode::ProviderUnavailable): static
    {
        return $this->state(fn () => [
            'status' => CrifReportStatus::Failed,
            'in_flight_key' => null,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'retryable' => $code->isRetryable(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function awaitingReconciliation(CrifErrorCode $code = CrifErrorCode::Timeout): static
    {
        return $this->state(fn () => [
            'status' => CrifReportStatus::ReconciliationRequired,
            'error_code' => $code,
            'error_message' => $code->safeMessage(),
            'processing_started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
