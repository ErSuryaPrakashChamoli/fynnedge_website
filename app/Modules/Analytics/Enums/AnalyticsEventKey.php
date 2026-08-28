<?php

namespace App\Modules\Analytics\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The fixed set of funnel milestones tracked across every loan product's journey —
 * from starting an application through to submitting it to a lender. This is a
 * structural, code-level funnel (not admin-configurable), unlike the journey steps
 * themselves.
 */
enum AnalyticsEventKey: string implements HasLabel
{
    case JourneyStarted = 'journey_started';
    case JourneyStepCompleted = 'journey_step_completed';
    case JourneyCompleted = 'journey_completed';
    case EligibilityEvaluated = 'eligibility_evaluated';
    case LenderSelected = 'lender_selected';
    case DocumentUploaded = 'document_uploaded';
    case ApplicationSubmitted = 'application_submitted';

    public function getLabel(): string
    {
        return match ($this) {
            self::JourneyStarted => 'Journey started',
            self::JourneyStepCompleted => 'Journey step completed',
            self::JourneyCompleted => 'Journey completed',
            self::EligibilityEvaluated => 'Eligibility evaluated',
            self::LenderSelected => 'Lender selected',
            self::DocumentUploaded => 'Document uploaded',
            self::ApplicationSubmitted => 'Application submitted',
        };
    }
}
