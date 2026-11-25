<?php

namespace App\Modules\Leads\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\LoanProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Services\LeadInbox;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Throwable;

/**
 * One row of the Lead Inbox — a read-only projection built by
 * {@see LeadInbox}. There is no `lead_inbox` table:
 * every query runs against the UNION subquery, and saving is refused.
 *
 * @property LeadSource $source
 * @property int $source_id
 * @property string $record_key
 */
class LeadInboxEntry extends Model
{
    protected $table = 'lead_inbox';

    protected $primaryKey = 'lead_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'source_id' => 'integer',
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
            'lms_at' => 'datetime',
            'repeat_count' => 'integer',
        ];
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('Lead Inbox rows are read-only; edit the source record instead.');
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * The form, bureau or purpose behind the row, in words.
     */
    public function detailLabel(): ?string
    {
        if (blank($this->detail)) {
            return null;
        }

        $enum = match ($this->source) {
            LeadSource::Enquiry => EnquiryType::tryFrom($this->detail),
            LeadSource::CreditScoreCheck => BureauName::tryFrom($this->detail),
            LeadSource::OtpVerification => MobileOtpPurpose::tryFrom($this->detail),
            LeadSource::OnlineApplication => null,
            LeadSource::LoanApplication => null,
        };

        return match (true) {
            $enum !== null => $enum->getLabel(),
            $this->source === LeadSource::OnlineApplication => 'Application journey',
            default => $this->detail,
        };
    }

    public function statusLabel(): ?string
    {
        if (blank($this->status)) {
            return null;
        }

        $enum = match ($this->source) {
            LeadSource::Enquiry => EnquiryStatus::tryFrom($this->status),
            LeadSource::CreditScoreCheck => CreditCheckStatus::tryFrom($this->status),
            LeadSource::OnlineApplication => JourneySessionStatus::tryFrom($this->status),
            LeadSource::LoanApplication => ApplicationStatus::tryFrom($this->status),
            LeadSource::OtpVerification => MobileOtpStatus::tryFrom($this->status),
        };

        return $enum?->getLabel() ?? $this->status;
    }

    public function syncStatus(): FynnOnSyncStatus
    {
        return FynnOnSyncStatus::fromAction($this->lms_status);
    }

    /**
     * The source record's own admin screen, or null if the user cannot open it.
     */
    public function recordUrl(): ?string
    {
        $resource = $this->source->resourceClass();

        try {
            return $resource::canAccess() ? $resource::getUrl($this->source->recordPage(), ['record' => $this->record_key]) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The address part of the source page (no domain, no query string) for
     * display and grouping, or null when the source did not record one.
     */
    public function sourcePath(): ?string
    {
        return self::pathOf($this->source_page);
    }

    public static function pathOf(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? '/'.ltrim($path, '/') : '/';
    }
}
