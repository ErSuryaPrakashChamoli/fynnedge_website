<?php

namespace App\Modules\Equifax\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxReconciliationResolution;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Journey\Models\JourneySession;
use Carbon\CarbonInterface;
use Database\Factories\EquifaxCreditRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One paid Equifax request through Surepass (credit-report-v2): a score or a
 * full report. Created only by App\Modules\Equifax\Actions\RequestEquifaxCheck
 * — which owns the duplicate, reuse and consent rules — and advanced only by
 * ProcessEquifaxRequest, ReconcileEquifaxRequest and the stale/retention
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
    'pan_encrypted', 'full_name', 'mobile', 'gender',
    'score', 'provider', 'provider_client_id', 'provider_report_id', 'provider_message_code', 'http_status',
    'report_date', 'raw_response', 'summary',
    'error_code', 'error_message', 'retryable', 'times_reused', 'duplicates_blocked',
    'reconciliation_resolution', 'reconciliation_note', 'reconciled_by', 'reconciled_at',
    'pdf_path', 'pdf_fetched_at', 'pdf_error_code',
    'requested_at', 'processing_started_at', 'completed_at', 'purged_at',
])]
#[Hidden(['pan_encrypted', 'full_name', 'mobile', 'raw_response', 'summary', 'pan_hash', 'in_flight_key', 'request_fingerprint', 'pdf_path'])]
class EquifaxCreditRequest extends Model
{
    /** @use HasFactory<EquifaxCreditRequestFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): EquifaxCreditRequestFactory
    {
        return EquifaxCreditRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'product' => EquifaxProduct::class,
            'status' => EquifaxRequestStatus::class,
            'request_type' => EquifaxRequestType::class,
            'error_code' => EquifaxErrorCode::class,
            'reconciliation_resolution' => EquifaxReconciliationResolution::class,
            'gender' => Gender::class,
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
        $query->whereIn('status', EquifaxRequestStatus::inFlight());
    }

    /**
     * Successful results that can answer a new request for $product instead of
     * a new paid pull: a report request only by a full report inside the report
     * reuse period; a score request by either, inside the score reuse period
     * (a full report carries the score). A score result never answers a report
     * request.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReusableFor(Builder $query, EquifaxProduct $product): void
    {
        $query->where('status', EquifaxRequestStatus::Success)
            ->whereNull('purged_at')
            ->when(
                $product === EquifaxProduct::Report,
                fn (Builder $query) => $query
                    ->where('product', EquifaxProduct::Report)
                    ->where('completed_at', '>=', now()->subDays(EquifaxSettings::reportReuseDays())),
                fn (Builder $query) => $query
                    ->where('completed_at', '>=', now()->subDays(EquifaxSettings::scoreReuseDays())),
            );
    }

    public function reuseDays(): int
    {
        return $this->product === EquifaxProduct::Report ? EquifaxSettings::reportReuseDays() : EquifaxSettings::scoreReuseDays();
    }

    public function isReusable(): bool
    {
        return $this->status === EquifaxRequestStatus::Success
            && $this->purged_at === null
            && $this->completed_at !== null
            && $this->completed_at->gte(now()->subDays($this->reuseDays()));
    }

    public function reusableUntil(): ?CarbonInterface
    {
        return $this->status === EquifaxRequestStatus::Success && $this->completed_at
            ? $this->completed_at->copy()->addDays($this->reuseDays())
            : null;
    }

    /** A successful full report whose contents are still held. */
    public function hasReport(): bool
    {
        return $this->product === EquifaxProduct::Report
            && $this->status === EquifaxRequestStatus::Success
            && $this->purged_at === null
            && is_array($this->raw_response);
    }

    public function hasStoredPdf(): bool
    {
        return filled($this->pdf_path) && $this->purged_at === null;
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
