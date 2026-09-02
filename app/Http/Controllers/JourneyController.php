<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use App\Modules\CreditScore\Actions\RequestMobileOtp;
use App\Modules\CreditScore\Actions\VerifyMobileOtp;
use App\Modules\CreditScore\Models\MobileOtpChallenge;
use App\Modules\Journey\Actions\GoToPreviousJourneyStep;
use App\Modules\Journey\Actions\StartJourneySession;
use App\Modules\Journey\Actions\SubmitJourneyStepResponses;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Services\JourneyStepResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class JourneyController extends Controller
{
    public function pickProduct(): View
    {
        return view('journey.pick-product', [
            'loanProducts' => LoanProduct::query()->published()->orderedForDisplay()->get(),
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
                // Eligible results first, then ranked by lowest interest rate — a lender
                // product with no rate configured sorts to the back of its eligible/
                // not-eligible group. Uses bare two-argument comparators (not the
                // [callback, direction] pair form) — that form takes the *value*
                // through an intermediate array keyed by the sort value, and a huge
                // sentinel float there gets truncated to int by PHP and throws.
                'results' => $session->eligibilityResults()
                    ->with(['lenderProduct.lender', 'reasons'])
                    ->get()
                    ->sortBy([
                        fn ($a, $b) => ($a->status->value === 'eligible' ? 0 : 1) <=> ($b->status->value === 'eligible' ? 0 : 1),
                        fn ($a, $b) => ($a->lenderProduct->interest_rate_from !== null ? 0 : 1) <=> ($b->lenderProduct->interest_rate_from !== null ? 0 : 1),
                        fn ($a, $b) => (float) ($a->lenderProduct->interest_rate_from ?? 0) <=> (float) ($b->lenderProduct->interest_rate_from ?? 0),
                    ])
                    ->values(),
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

        // The 'phone' field key is shared by every loan product's basic-details step
        // (JourneySeeder::basicDetailsFields()), so this check applies uniformly without
        // per-product branching. It backstops the client-side OTP gate in field.blade.php —
        // a submission can only reach here with a phone value once that number has actually
        // been OTP-verified via sendPhoneOtp()/verifyPhoneOtp() below.
        if (array_key_exists('phone', $validated) && ($session->phone_verified_at === null || $session->phone_number !== $validated['phone'])) {
            throw ValidationException::withMessages([
                'phone' => 'Please verify your mobile number with an OTP before continuing.',
            ]);
        }

        $action->handle($session, $session->currentStep, $validated, $request->ip());

        return redirect()->route('journey.show', $session);
    }

    public function back(JourneySession $session, GoToPreviousJourneyStep $action): RedirectResponse
    {
        $session->load(['journeyDefinition.steps.fields', 'currentStep']);
        $action->handle($session);

        return redirect()->route('journey.show', $session);
    }

    public function sendPhoneOtp(JourneySession $session, Request $request, RequestMobileOtp $action): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ], [], ['phone' => 'mobile number']);

        ['challenge' => $challenge, 'code' => $code] = $action->handle($validated['phone'], $request->ip());

        return response()->json([
            'otp_challenge_id' => $challenge->public_id,
            // No SMS gateway is connected yet, so the code is handed straight back
            // instead of being texted — mirrors the credit-score check's demo mode.
            'demo_otp_code' => $code,
        ], 201);
    }

    public function verifyPhoneOtp(JourneySession $session, Request $request, VerifyMobileOtp $action): JsonResponse
    {
        $validated = $request->validate([
            'otp_challenge_id' => ['required', 'string'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $challenge = MobileOtpChallenge::query()->where('public_id', $validated['otp_challenge_id'])->first();

        if (! $challenge || ! $action->handle($challenge, $validated['otp_code'])) {
            return response()->json([
                'message' => 'That code is incorrect or has expired. You can resend a new one.',
            ], 422);
        }

        $session->update([
            'phone_number' => $challenge->mobile_number,
            'phone_verified_at' => now(),
        ]);

        return response()->json(['verified' => true]);
    }
}
