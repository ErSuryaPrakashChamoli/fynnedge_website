<?php

namespace App\Modules\Experian\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianReconciliationResolution;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Services\ExperianSettings;
use App\Modules\Journey\Models\JourneySession;
use Carbon\CarbonInterface;
use Database\Factories\ExperianCreditRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One paid Experian request through Surepass (credit-report-experian): a
 * score, a full report or a PDF report. Created only by App\Modules\Experian\Actions\RequestExperianCheck
 * — which owns the duplicate, reuse and consent rules — and advanced only by
 * ProcessExperianRequest, ReconcileExperianRequest and the stale/retention
 * commands. A fresh pull is a new row; earlier rows are never overwritten.
 *
 * Sensitive columns (PAN, name, mobile, the provider response, the derived
 * summary) are encrypted at rest, hidden from serialisation and excluded from
 * the audit trail.
 */
#[Fillable([
    'customer_id', 'journey_session_id', 'credit_consent_id', 'previous_request_id', 'requested_by',
    'product', 'request_type', 'channel', 'fresh_reason', 'status',
    'pan_hash', 'in_flight_key', 'request_fingerprint', 'pan_masked', 'mobile_masked',
    'pan_encrypted', 'full_name', 'mobile',
    'score', 'provider', 'provider_client_id', 'provider_report_id', 'provider_message_code', 'http_status',
    'report_date', 'raw_response', 'summary',
    'error_code', 'error_message', 'retryable', 'times_reused', 'duplicates_blocked',
    'reconciliation_resolution', 'reconciliation_note', 'reconciled_by', 'reconciled_at',
    'pdf_path', 'pdf_fetched_at', 'pdf_error_code',
    'requested_at', 'processing_started_at', 'completed_at', 'purged_at',
])]
#[Hidden(['pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'pan_hash', 'in_flight_key', 'request_fingerprint', 'pdf_path'])]
class ExperianCreditRequest extends Model
{
    /** @use HasFactory<ExperianCreditRequestFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): ExperianCreditRequestFactory
    {
        return ExperianCreditRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'product' => ExperianProduct::class,
            'status' => ExperianRequestStatus::class,
            'request_type' => ExperianRequestType::class,
            'error_code' => ExperianErrorCode::class,
            'reconciliation_resolution' => ExperianReconciliationResolution::class,
            'pan_encrypted' => 'encrypted',
            'full_name' => 'encrypted',
            'mobile' => 'encrypted',
            'raw_response' => 'encrypted:array',
            'summary' => 'encrypted:array',
            'retryable' => 'boolean',
            'report_date' => 'date',
            'reconciled_at' => 'datetime',
            'pdf_fetched_at' => 'datetime',
            'requested_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journeySession(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(CreditConsent::class, 'credit_consent_id');
    }

    public function previousRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_request_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForPan(Builder $query, string $panHash): void
    {
        $query->where('pan_hash', $panHash);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', ExperianRequestStatus::inFlight());
    }

    /**
     * Successful results that can answer a new request for $product instead of
     * a new paid pull:
     *
     *  - a report request only by a full report inside the report reuse period;
     *  - a PDF request only by a PDF whose file is still stored, inside the
     *    report reuse period;
     *  - a score request by any of the three inside the score reuse period
     *    (reports and PDFs carry the score).
     *
     * A score never answers a report or PDF request.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReusableFor(Builder $query, ExperianProduct $product): void
    {
        $query->where('status', ExperianRequestStatus::Success)
            ->whereNull('purged_at');

        match ($product) {
            ExperianProduct::Report => $query
                ->where('product', ExperianProduct::Report)
                ->where('completed_at', '>=', now()->subDays(ExperianSettings::reportReuseDays())),
            ExperianProduct::Pdf => $query
                ->where('product', ExperianProduct::Pdf)
                ->whereNotNull('pdf_path')
                ->where('completed_at', '>=', now()->subDays(ExperianSettings::reportReuseDays())),
            ExperianProduct::Score => $query
                ->where('completed_at', '>=', now()->subDays(ExperianSettings::scoreReuseDays())),
        };
    }

    public function reuseDays(): int
    {
        return $this->product === ExperianProduct::Score ? ExperianSettings::scoreReuseDays() : ExperianSettings::reportReuseDays();
    }

    public function isReusable(): bool
    {
        return $this->status === ExperianRequestStatus::Success
            && $this->purged_at === null
            && $this->completed_at !== null
            && ($this->product !== ExperianProduct::Pdf || $this->hasStoredPdf())
            && $this->completed_at->gte(now()->subDays($this->reuseDays()));
    }

    public function reusableUntil(): ?CarbonInterface
    {
        return $this->status === ExperianRequestStatus::Success && $this->completed_at
            ? $this->completed_at->copy()->addDays($this->reuseDays())
            : null;
    }

    /** A successful full report whose contents are still held. */
    public function hasReport(): bool
    {
        return $this->product === ExperianProduct::Report
            && $this->status === ExperianRequestStatus::Success
            && $this->purged_at === null
            && is_array($this->raw_response);
    }

    public function hasStoredPdf(): bool
    {
        return filled($this->pdf_path) && $this->purged_at === null;
    }

    /** A successful PDF request whose provider response (and so its link) is still held. */
    public function isPdfResult(): bool
    {
        return $this->product === ExperianProduct::Pdf
            && $this->status === ExperianRequestStatus::Success
            && $this->purged_at === null
            && is_array($this->raw_response);
    }

    public function customerName(): string
    {
        return trim((string) $this->full_name) ?: ($this->customer?->full_name ?? '—');
    }

    /**
     * Records an access or workflow event (viewed, reused, blocked, PDF…) in the
     * SAME audit_logs table the Auditable trait writes model changes to. Values
     * passed here must never contain the PAN, mobile number or report contents.
     *
     * @param  array<string, scalar|null>  $details
     */
    public function recordEvent(string $action, array $details = [], ?int $userId = null): void
    {
        $this->auditLogs()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'changes' => collect($details)->map(fn ($value) => ['old' => null, 'new' => $value])->all() ?: null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return [
            'pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'score',
            'pan_hash', 'in_flight_key', 'request_fingerprint', 'pdf_path',
        ];
    }
}
