<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The structured, validated view of a debt consolidation case for an
 * authorised analysis service (e.g. the future FynnAI). It carries figures,
 * statuses and the stored calculation — never the customer's name, mobile,
 * email, PAN, account numbers or documents — and it is read-only: the
 * calculation and every lender decision stay in this system.
 *
 * @mixin DebtApplication
 */
class DebtCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->latestSnapshot;

        return [
            'reference' => $this->reference,
            'applicant_type' => $this->applicant_type->value,
            'status' => $this->status->value,
            'city' => $this->city,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'liabilities' => $this->liabilities->map(fn (DebtLiability $liability): array => [
                'id' => $liability->public_id,
                'type' => $liability->liability_type->value,
                'lender' => $liability->lender_name,
                'secured' => $liability->is_secured,
                'outstanding_principal' => (float) $liability->outstanding_principal,
                'annual_interest_rate' => (float) $liability->interest_rate,
                'monthly_payment' => $liability->monthly_payment === null ? null : (float) $liability->monthly_payment,
                'remaining_months' => $liability->remaining_months,
                'account_status' => $liability->account_status?->value,
                'consolidation_mode' => $liability->consolidation_mode->value,
                'verification_status' => $liability->verification_status->value,
            ])->values(),
            'assessment' => $snapshot === null ? null : [
                'id' => $snapshot->public_id,
                'calculated_at' => $snapshot->created_at?->toIso8601String(),
                'formula_version' => $snapshot->formula_version,
                'scoring_version' => $snapshot->scoring_version,
                'inputs_hash' => $snapshot->inputs_hash,
                'results' => $snapshot->results,
            ],
            'lender_applications' => $this->lenderApplications->map(fn (DebtLenderApplication $application): array => [
                'id' => $application->public_id,
                'lender' => $application->lender_name,
                'product' => $application->product_name,
                'status' => $application->status->value,
                'sanctioned_amount' => $application->sanctioned_amount === null ? null : (float) $application->sanctioned_amount,
                'disbursed_amount' => $application->disbursed_amount === null ? null : (float) $application->disbursed_amount,
            ])->values(),
            'closures' => $this->closures->map(fn (DebtClosure $closure): array => [
                'liability_id' => $closure->liability?->public_id,
                'type' => $closure->closure_type->value,
                'status' => $closure->status->value,
                'verified_at' => $closure->verified_at?->toIso8601String(),
            ])->values(),
            'disclaimer' => 'Figures are calculated by FynnEdge and are not lender decisions. Do not modify or re-derive them; explain them.',
        ];
    }
}
