<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\DebtConsolidation\Enums\ReportType;
use Database\Factories\DebtReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * A generated PDF report, stored encrypted on the private disk with its
 * SHA-256 so a downloaded copy can be checked against the record.
 */
#[Fillable([
    'debt_application_id', 'report_type', 'version', 'debt_calculation_snapshot_id', 'formula_version', 'disk', 'path',
    'sha256', 'size', 'is_customer_visible', 'generated_by', 'generated_at',
])]
class DebtReport extends Model
{
    /** @use HasFactory<DebtReportFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): DebtReportFactory
    {
        return DebtReportFactory::new();
    }

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'is_customer_visible' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DebtCalculationSnapshot::class, 'debt_calculation_snapshot_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function contents(): string
    {
        return Crypt::decryptString((string) Storage::disk($this->disk)->get($this->path));
    }

    public function downloadName(): string
    {
        return 'fynnedge-'.str($this->report_type->value)->slug().'-'.strtolower((string) $this->application?->reference).'-v'.$this->version.'.pdf';
    }
}
