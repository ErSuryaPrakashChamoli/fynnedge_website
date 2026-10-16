<?php

namespace App\Modules\Experian\Actions;

use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\DataTransferObjects\SurepassExperianResult;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianFingerprint;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a public score page's paid Surepass Experian call — the Free
 * Experian Score page, and the CIBIL page while Experian stands in for it — as
 * an Experian request (channel "website"), so it shows in the Experian admin —
 * dashboard, Credit Reports, failed / reconciliation lists, audit — and its
 * full report is kept (encrypted, under the Experian retention period) instead
 * of being thrown away after the score is read.
 *
 * start() runs BEFORE the call: the row is created as Processing with the
 * visitor's consent recorded (OTP-verified mobile + consent tick), so a call
 * that dies midway is caught by `experian:flag-stale` like any other.
 * finish() stores the answer through ProcessExperianRequest::record(), with
 * exactly the staff rules for success, failure and reconciliation. Neither
 * ever stops the visitor's check: a recording failure is logged and the score
 * is still shown.
 *
 * The row holds no in-flight key (the public page has its own per-person
 * hold), and a successful report may answer a later staff request for the
 * same PAN inside the reuse period — the existing cross-record reuse rule.
 */
class RecordWebsiteExperianCheck
{
    public const CHANNEL = 'website';

    public function __construct(private readonly ProcessExperianRequest $process) {}

    /**
     * @return array{request: ExperianCreditRequest, consent: CreditConsent}|null
     */
    public function start(CreditScoreCheck $check, ExperianProduct $product): ?array
    {
        try {
            return DB::transaction(function () use ($check, $product): array {
                $pan = PanNumber::normalize($check->pan_number);
                $mobile = MobileNumber::normalize($check->mobile_number);
                $name = trim(preg_replace('/\s+/', ' ', (string) $check->full_name) ?? '');
                $customer = Customer::query()->where('phone', $mobile)->latest('id')->first();

                $consent = CreditConsent::query()->create([
                    'customer_id' => $customer?->id,
                    'pan_number' => $pan,
                    'date_of_birth' => $check->date_of_birth,
                    'purpose' => 'Free '.$check->bureau->getLabel().' score check on the website',
                    'method' => ConsentMethod::Otp,
                    'evidence_reference' => 'Website score check '.$check->public_id.' (OTP-verified mobile, consent box ticked)',
                    'terms_version' => config('services.credit_bureau.terms_version', 'v1'),
                    'ip_address' => $check->ip_address,
                    'status' => ConsentStatus::Given,
                    'consented_at' => $check->consent_given_at ?? now(),
                ]);

                $request = ExperianCreditRequest::query()->create([
                    'customer_id' => $customer?->id,
                    'credit_consent_id' => $consent->id,
                    'product' => $product,
                    'request_type' => ExperianRequestType::Standard,
                    'channel' => self::CHANNEL,
                    'status' => ExperianRequestStatus::Processing,
                    'pan_hash' => PanNumber::hash($pan),
                    'in_flight_key' => null,
                    'request_fingerprint' => ExperianFingerprint::request($pan, $name, $mobile),
                    'pan_masked' => PanNumber::mask($pan),
                    'mobile_masked' => MobileNumber::mask($mobile),
                    'pan_encrypted' => $pan,
                    'full_name' => $name,
                    'mobile' => $mobile,
                    'provider' => 'surepass',
                    'requested_at' => now(),
                    'processing_started_at' => now(),
                ]);

                $request->recordEvent('website_check_started', ['page' => $check->bureau->value, 'check' => $check->public_id]);

                return ['request' => $request, 'consent' => $consent];
            });
        } catch (Throwable $exception) {
            Log::error('Website Experian check could not be recorded', ['check' => $check->public_id, 'exception' => $exception::class]);

            return null;
        }
    }

    public function finish(ExperianCreditRequest $request, SurepassExperianResult $result): void
    {
        try {
            $this->process->record($request, $result);
        } catch (Throwable $exception) {
            Log::error('Website Experian result could not be recorded', ['request' => $request->public_id, 'exception' => $exception::class]);
        }
    }
}
