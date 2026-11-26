<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\Customers\Actions\ResolveCustomerAccount;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Opens the case for an OTP-verified mobile, or returns the one already open.
 *
 * Duplicate detection: one open, unsubmitted case per verified mobile and
 * applicant type. Starting again (another tab, the enquiry form then the
 * assessment) continues that case instead of creating a second one. The
 * customer portal account comes from the existing ResolveCustomerAccount
 * (the same rule the credit-score check uses), so the customer can resume
 * from /my-profile.
 */
class OpenDebtApplication
{
    public function __construct(private readonly ResolveCustomerAccount $accounts) {}

    /**
     * @param  array{full_name?: ?string, email?: ?string, city?: ?string, source_url?: ?string, utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string}  $details
     */
    public function handle(string $verifiedMobile, ApplicantType $applicantType, array $details = []): DebtApplication
    {
        DebtConsolidationSettings::ensureAccepting($applicantType);

        return Cache::lock('debt-application-open:'.$verifiedMobile, 10)->block(5, function () use ($verifiedMobile, $applicantType, $details): DebtApplication {
            return DB::transaction(function () use ($verifiedMobile, $applicantType, $details): DebtApplication {
                $customer = $this->accounts->forVerifiedMobile($verifiedMobile);

                $existing = DebtApplication::query()
                    ->ownedBy($customer)
                    ->where('applicant_type', $applicantType)
                    ->whereNull('submitted_at')
                    ->open()
                    ->latest('id')
                    ->first();

                // A case staff opened over the phone for this number, not yet claimed:
                // the customer has now proved the number, so it becomes theirs.
                $existing ??= DebtApplication::query()
                    ->whereNull('customer_id')
                    ->where('mobile', $verifiedMobile)
                    ->where('applicant_type', $applicantType)
                    ->open()
                    ->latest('id')
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill([
                        'customer_id' => $customer->getKey(),
                        'full_name' => $existing->full_name ?: ($details['full_name'] ?? null),
                        'email' => $existing->email ?: ($details['email'] ?? null),
                        'city' => $existing->city ?: ($details['city'] ?? null),
                        'mobile_verified_at' => now(),
                        'last_customer_activity_at' => now(),
                    ])->save();

                    return $existing;
                }

                $application = DebtApplication::query()->create([
                    'customer_id' => $customer->getKey(),
                    'applicant_type' => $applicantType,
                    'status' => DebtApplicationStatus::Draft,
                    'mobile' => $verifiedMobile,
                    'mobile_verified_at' => now(),
                    'full_name' => $details['full_name'] ?? $customer->full_name,
                    'email' => $details['email'] ?? $customer->email,
                    'city' => $details['city'] ?? $customer->city,
                    'source_url' => isset($details['source_url']) ? mb_substr((string) $details['source_url'], 0, 255) : null,
                    'utm_source' => $details['utm_source'] ?? null,
                    'utm_medium' => $details['utm_medium'] ?? null,
                    'utm_campaign' => $details['utm_campaign'] ?? null,
                    'last_customer_activity_at' => now(),
                ]);

                $application->recordStatusChange(null, DebtApplicationStatus::Draft->value, ActorType::Customer);

                return $application;
            });
        });
    }
}
