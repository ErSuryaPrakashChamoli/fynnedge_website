<?php

namespace App\Modules\CreditBureau\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReconciliationResolution;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use Carbon\CarbonInterface;
use Database\Factories\CrifReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One paid CRIF report attempt through Surepass. Created only by
 * App\Modules\CreditBureau\Actions\RequestCrifReport — which owns the
 * duplicate, reuse and consent rules — and advanced only by
 * ProcessCrifReport, ReconcileCrifReport and the stale/retention commands.
 *
 * Sensitive columns (PAN, names, mobile, the provider response) are encrypted
 * at rest, hidden from serialisation, and excluded from the audit trail.
 */
#[Fillable([
    'customer_id', 'journey_session_id', 'credit_consent_id', 'previous_report_id', 'requested_by',
    'request_type', 'fresh_reason', 'status',
    'pan_hash', 'in_flight_key', 'request_fingerprint', 'pan_masked', 'mobile_masked',
    'pan_encrypted', 'first_name', 'last_name', 'mobile',
    'score', 'provider', 'provider_client_id', 'provider_report_id', 'provider_inquiry_id',
    'provider_message_code', 'http_status', 'report_requested_on', 'report_issued_on', 'raw_response',
    'error_code', 'error_message', 'retryable', 'times_reused', 'duplicates_blocked',
    'reconciliation_resolution', 'reconciliation_note', 'reconciled_by', 'reconciled_at',
    'pdf_path', 'pdf_fetched_at', 'pdf_error_code',
    'requested_at', 'processing_started_at', 'completed_at', 'purged_at',
])]
#[Hidden(['pan_encrypted', 'first_name', 'last_name', 'mobile', 'raw_response', 'pan_hash', 'in_flight_key', 'request_fingerprint', 'pdf_path'])]
class CrifReport extends Model
{
    /** @use HasFactory<CrifReportFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected static function newFactory(): CrifReportFactory
    {
        return CrifReportFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => CrifReportStatus::class,
            'request_type' => CrifRequestType::class,
            'error_code' => CrifErrorCode::class,
            'reconciliation_resolution' => CrifReconciliationResolution::class,
            'pan_encrypted' => 'encrypted',
            'first_name' => 'encrypted',
            'last_name' => 'encrypted',
            'mobile' => 'encrypted',
            'raw_response' => 'encrypted:array',
            'retryable' => 'boolean',
            'report_requested_on' => 'date',
            'report_issued_on' => 'date',
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

    public function previousReport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_report_id');
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
     * Successful reports still inside the configured reuse period — the ones a
     * standard request is answered with instead of a new paid pull.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReusable(Builder $query): void
    {
        $query->where('status', CrifReportStatus::Success)
            ->whereNull('purged_at')
            ->where('completed_at', '>=', now()->subDays(CrifSettings::reuseDays()));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', CrifReportStatus::inFlight());
    }

    public function isReusable(): bool
    {
        return $this->status === CrifReportStatus::Success
            && $this->purged_at === null
            && $this->completed_at !== null
            && $this->completed_at->gte(now()->subDays(CrifSettings::reuseDays()));
    }

    public function reusableUntil(): ?CarbonInterface
    {
        return $this->status === CrifReportStatus::Success && $this->completed_at
            ? $this->completed_at->copy()->addDays(CrifSettings::reuseDays())
            : null;
    }

    public function hasReport(): bool
    {
        return $this->status === CrifReportStatus::Success && $this->purged_at === null && is_array($this->raw_response);
    }

    public function hasStoredPdf(): bool
    {
        return filled($this->pdf_path) && $this->purged_at === null;
    }

    public function customerName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->customer?->full_name ?? '—');
    }

    /**
     * Records an access or workflow event (viewed, reused, blocked, PDF…) in the
     * SAME audit_logs table the Auditable trait writes model changes to, so a
     * report's History tab shows who touched it and why in one place. Values
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
            'pan_encrypted', 'first_name', 'last_name', 'mobile', 'raw_response', 'score',
            'pan_hash', 'in_flight_key', 'request_fingerprint', 'pdf_path',
        ];
    }
}
