<?php

namespace App\Modules\CreditBureau\Services;

use App\Models\User;
use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Customers\Models\Customer;

/**
 * The one set of consent rules for a paid bureau pull, shared by every bureau
 * module (Equifax uses it; CRIF's RequestCrifReport applies the same rules).
 * A consent authorises a pull only when it is Given, belongs to this customer,
 * was given for this PAN (when it names one), and is not older than the
 * bureau's validity period — "consent = Y" is never sent just because a
 * customer exists.
 */
final class CreditConsentGate
{
    /**
     * Why the consent cannot authorise this pull, or null when it can.
     */
    public static function refusal(CreditConsentInput $input, Customer $customer, string $normalizedPan, int $validityDays): ?string
    {
        $earliest = now()->subDays($validityDays);

        if ($input->existing) {
            $consent = $input->existing;
            $consentCustomerId = $consent->customer_id ?? $consent->journeySession?->customer_id;

            return match (true) {
                $consent->status !== ConsentStatus::Given => 'The customer\'s consent has been withdrawn. Record fresh consent before requesting a report.',
                $consentCustomerId !== $customer->id => 'That consent belongs to a different customer.',
                $consent->consented_at === null || $consent->consented_at->lt($earliest) => "The customer's consent is older than {$validityDays} days. Record fresh consent before requesting a report.",
                filled($consent->pan_number) && PanNumber::normalize($consent->pan_number) !== $normalizedPan => 'The customer consented for a different PAN than the one entered.',
                default => null,
            };
        }

        return match (true) {
            $input->method === null || ! array_key_exists($input->method->value, ConsentMethod::employeeRecordable()) => 'Choose how the customer gave consent.',
            mb_strlen((string) $input->evidenceReference) < 3 => 'Enter the consent evidence reference (e.g. the form number, call ID or email subject).',
            $input->consentedAt === null || $input->consentedAt->isFuture() || $input->consentedAt->lt($earliest) => "The consent date must be within the last {$validityDays} days and not in the future.",
            ! $input->attested => 'Confirm that the customer consented to this credit report.',
            default => null,
        };
    }

    /**
     * The CreditConsent the pull is recorded against: the existing one, or a new
     * employee-recorded consent with its evidence.
     */
    public static function record(CreditConsentInput $input, Customer $customer, User $actor, string $purpose): CreditConsent
    {
        if ($input->existing) {
            return $input->existing;
        }

        return CreditConsent::query()->create([
            'customer_id' => $customer->id,
            'journey_session_id' => null,
            'purpose' => $purpose,
            'method' => $input->method,
            'evidence_reference' => mb_substr((string) $input->evidenceReference, 0, 255),
            'recorded_by' => $actor->id,
            'terms_version' => config('services.credit_bureau.terms_version', 'v1'),
            'status' => ConsentStatus::Given,
            'consented_at' => $input->consentedAt,
        ]);
    }
}
