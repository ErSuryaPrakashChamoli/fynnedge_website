<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;

/**
 * Records one purpose-specific consent with the exact wording, its version
 * and SHA-256, the moment, and where it came from. Lender-sharing consent
 * names the one lender it covers.
 */
class RecordDebtConsent
{
    public function handle(
        DebtApplication $application,
        ConsentPurpose $purpose,
        string $method = DebtConsent::METHOD_WEB_CHECKBOX,
        ?LenderProduct $lenderProduct = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $recordedBy = null,
    ): DebtConsent {
        $lenderName = $lenderProduct?->lender?->getRawOriginal('name');
        $text = DebtConsolidationSettings::consentText($purpose, $lenderName);

        $consent = $application->consents()->create([
            'customer_id' => $application->customer_id,
            'purpose' => $purpose,
            'lender_product_id' => $lenderProduct?->getKey(),
            'consent_text' => $text,
            'text_version' => DebtConsolidationSettings::consentVersion(),
            'text_hash' => hash('sha256', $text),
            'method' => $method,
            'granted_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'recorded_by' => $recordedBy,
        ]);

        $application->recordEvent('consent_recorded', ['purpose' => $purpose->value, 'consent' => $consent->public_id], $recordedBy);

        return $consent;
    }

    public function hasActive(DebtApplication $application, ConsentPurpose $purpose, ?LenderProduct $lenderProduct = null): bool
    {
        return $application->consents()
            ->active()
            ->where('purpose', $purpose)
            ->when($lenderProduct !== null, fn ($query) => $query->where('lender_product_id', $lenderProduct->getKey()))
            ->exists();
    }
}
