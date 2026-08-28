<?php

namespace App\Modules\Journey\Actions;

use App\Models\LoanProduct;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Services\JourneyStepResolver;
use Illuminate\Http\Request;

class StartJourneySession
{
    public function __construct(private readonly JourneyStepResolver $resolver) {}

    public function handle(LoanProduct $loanProduct, Request $request): ?JourneySession
    {
        $definition = JourneyDefinition::query()
            ->where('loan_product_id', $loanProduct->id)
            ->where('status', JourneyDefinitionStatus::Active)
            ->latest('version')
            ->first();

        if (! $definition) {
            return null;
        }

        $firstStep = $this->resolver->firstStep($definition);

        return JourneySession::query()->create([
            'loan_product_id' => $loanProduct->id,
            'journey_definition_id' => $definition->id,
            'current_step_id' => $firstStep?->id,
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'referrer' => $request->headers->get('referer'),
            'landing_page' => $request->fullUrl(),
        ]);
    }
}
