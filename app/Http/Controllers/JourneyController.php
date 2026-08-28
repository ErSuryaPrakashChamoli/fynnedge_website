<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use App\Modules\Journey\Actions\GoToPreviousJourneyStep;
use App\Modules\Journey\Actions\StartJourneySession;
use App\Modules\Journey\Actions\SubmitJourneyStepResponses;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Services\JourneyStepResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JourneyController extends Controller
{
    public function pickProduct(): View
    {
        return view('journey.pick-product', [
            'loanProducts' => LoanProduct::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function start(LoanProduct $loanProduct, Request $request, StartJourneySession $action): RedirectResponse
    {
        $session = $action->handle($loanProduct, $request);

        if (! $session) {
            return back()->with('status', 'Applications for this product aren\'t open yet — please check back soon.');
        }

        return redirect()->route('journey.show', $session);
    }

    public function show(JourneySession $session, JourneyStepResolver $resolver): View
    {
        $session->load(['loanProduct', 'journeyDefinition.steps.fields', 'currentStep']);

        if ($session->status === JourneySessionStatus::Completed || ! $session->currentStep) {
            return view('journey.complete', [
                'session' => $session,
                'results' => $session->eligibilityResults()
                    ->with(['lenderProduct.lender', 'reasons'])
                    ->get()
                    ->sortByDesc(fn ($result) => $result->status->value === 'eligible'),
            ]);
        }

        $responses = $session->responsesByKey();

        return view('journey.show', [
            'session' => $session,
            'step' => $session->currentStep,
            'steps' => $resolver->visibleSteps($session->journeyDefinition, $responses),
            'fields' => $session->currentStep->fields,
            'responses' => $responses,
            'progress' => $resolver->progress($session->journeyDefinition, $session->currentStep, $responses),
        ]);
    }

    public function update(
        JourneySession $session,
        Request $request,
        JourneyStepResolver $resolver,
        SubmitJourneyStepResponses $action,
    ): RedirectResponse {
        $session->load(['journeyDefinition.steps.fields', 'currentStep']);
        abort_if($session->status === JourneySessionStatus::Completed || ! $session->currentStep, 404);

        // Fields conditional on another field from this same step (e.g. "company name" when
        // employment_type=salaried) must see this request's own input, not just prior steps'
        // already-saved responses — otherwise a same-step reveal is silently skipped.
        $mergedResponses = [...$session->responsesByKey(), ...$request->all()];
        $rules = $resolver->validationRulesFor($session->currentStep, $mergedResponses);

        $validated = Validator::make($request->all(), $rules)->validate();

        $action->handle($session, $session->currentStep, $validated, $request->ip());

        return redirect()->route('journey.show', $session);
    }

    public function back(JourneySession $session, GoToPreviousJourneyStep $action): RedirectResponse
    {
        $session->load(['journeyDefinition.steps.fields', 'currentStep']);
        $action->handle($session);

        return redirect()->route('journey.show', $session);
    }
}
