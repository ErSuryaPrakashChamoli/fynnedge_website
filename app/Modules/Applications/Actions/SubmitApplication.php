<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;

class SubmitApplication
{
    /**
     * @return array<int, string> validation errors — empty means it was submitted
     */
    public function handle(Application $application): array
    {
        if (! $application->hasAllRequiredDocuments()) {
            return ['Please upload every required document before submitting.'];
        }

        $application->update([
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return [];
    }
}
