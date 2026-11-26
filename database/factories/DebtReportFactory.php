<?php

namespace Database\Factories;

use App\Modules\DebtConsolidation\Enums\ReportType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtReport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DebtReport>
 */
class DebtReportFactory extends Factory
{
    protected $model = DebtReport::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'report_type' => ReportType::PreliminaryAssessment,
            'version' => 1,
            'disk' => 'local',
            'path' => 'debt-consolidation/reports/'.Str::uuid().'.pdf.enc',
            'sha256' => hash('sha256', 'report'),
            'size' => 100,
            'generated_at' => now(),
        ];
    }
}
