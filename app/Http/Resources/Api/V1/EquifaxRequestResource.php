<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only shape an Equifax request leaves the server in through the API.
 *
 *  - Always: status, product, score, masked PAN / mobile, dates, safe error.
 *  - `summary` (headline figures): only with ViewReport.
 *  - `report` (the full report, identifiers masked): only with ViewReport AND
 *    ?include=report.
 *  - `raw` (the complete, unmasked Surepass response): only with ViewRaw AND
 *    ?include=raw.
 *
 * Never: the full PAN or mobile, the token, the provider's own error text, the
 * stored PDF path or the temporary provider PDF link.
 *
 * @mixin EquifaxCreditRequest
 */
class EquifaxRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $includes = array_map('trim', explode(',', (string) $request->query('include', '')));
        $canSeeReport = (bool) $user?->can('viewReport', $this->resource);
        $isReport = $this->product === EquifaxProduct::Report;

        return [
            'id' => $this->public_id,
            'customer_id' => $this->customer?->public_id,
            'application_id' => $this->journeySession?->public_id,
            'product' => $this->product->value,
            'request_type' => $this->request_type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'score' => $this->score,
            'pan_masked' => $this->pan_masked,
            'mobile_masked' => $this->mobile_masked,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'report_date' => $this->report_date?->toDateString(),
            'reusable' => $this->isReusable(),
            'reusable_until' => $this->reusableUntil()?->toIso8601String(),
            'provider_reference' => $this->provider_report_id,
            'error' => $this->error_code ? ['code' => $this->error_code->value, 'message' => $this->error_code->safeMessage()] : null,
            'pdf_stored' => $this->hasStoredPdf(),
            'summary' => $this->when($canSeeReport && $isReport && is_array($this->summary), fn () => $this->summary),
            'report' => $this->when(
                $canSeeReport && in_array('report', $includes, true) && $this->hasReport(),
                fn () => $this->reportSections(new EquifaxReportDocument($this->raw_response)),
            ),
            'raw' => $this->when(
                (bool) $user?->can('viewRaw', $this->resource) && in_array('raw', $includes, true) && is_array($this->raw_response),
                fn () => $this->raw_response,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportSections(EquifaxReportDocument $document): array
    {
        return [
            'header' => $document->header(),
            'scores' => $document->scores(),
            'personal_info' => $document->personalInfo(),
            'identities' => $document->identities(),
            'addresses' => $document->addresses(),
            'phones' => $document->phones(),
            'emails' => $document->emails(),
            'accounts_summary' => $document->accountsSummary(),
            'accounts' => $document->accounts(),
            'enquiry_summary' => $document->enquirySummary(),
            'enquiries' => $document->enquiries(),
            'other_sections' => $document->otherSections(),
        ];
    }
}
