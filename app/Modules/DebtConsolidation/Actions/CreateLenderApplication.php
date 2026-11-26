<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use Illuminate\Validation\ValidationException;

/**
 * Opens an application to one lender for a case. It can only be created
 * once the customer has consented to sharing their details with THAT lender
 * (given in the portal, or recorded by staff from a documented consent).
 */
class CreateLenderApplication
{
    public function handle(DebtApplication $application, LenderProduct $lenderProduct, ?float $appliedAmount, int $userId, ?string $notes = null): DebtLenderApplication
    {
        $lenderProduct->loadMissing('lender', 'loanProduct');

        $consent = $application->consents()->active()
            ->where('purpose', ConsentPurpose::LenderSharing)
            ->where('lender_product_id', $lenderProduct->getKey())
            ->latest('id')
            ->first();

        if (! $consent instanceof DebtConsent) {
            throw ValidationException::withMessages(['lender_product_id' => 'The customer has not consented to sharing their details with this lender. Ask the customer to consent in their profile, or record their documented consent first.']);
        }

        $lenderApplication = $application->lenderApplications()->create([
            'lender_product_id' => $lenderProduct->getKey(),
            'lender_name' => (string) $lenderProduct->lender?->getRawOriginal('name'),
            'product_name' => (string) $lenderProduct->loanProduct?->getRawOriginal('name'),
            'debt_lender_match_id' => $application->lenderMatches()->where('lender_product_id', $lenderProduct->getKey())->value('id'),
            'debt_consent_id' => $consent->getKey(),
            'status' => LenderApplicationStatus::NotSubmitted,
            'applied_amount' => $appliedAmount,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        $lenderApplication->recordStatusChange(null, LenderApplicationStatus::NotSubmitted->value, ActorType::Staff, $userId);

        return $lenderApplication;
    }
}
