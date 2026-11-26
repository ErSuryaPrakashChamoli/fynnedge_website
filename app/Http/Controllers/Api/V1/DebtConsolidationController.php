<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DebtCaseResource;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/external/debt-consolidation/cases/{reference} — the read-only
 * integration point for a future analysis service such as FynnAI.
 *
 * Three gates, all required: a valid API key (auth:api-client, IP allow-list,
 * revocable in Administration → API Clients); the key's service account may
 * view debt consolidation cases (role permission); and the customer has an
 * active "external analysis" consent on this case. Every read is recorded on
 * the case's activity log.
 */
class DebtConsolidationController extends Controller
{
    public function show(Request $request, string $reference): JsonResponse
    {
        // Permission first, so a key that may not read cases cannot learn which references exist.
        Gate::authorize('view', new DebtApplication);

        $application = DebtApplication::query()->where('reference', $reference)->first();

        if ($application === null) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'No case with that reference.']], 404);
        }

        Gate::authorize('view', $application);

        $consented = $application->consents()->active()->where('purpose', ConsentPurpose::ExternalAnalysis)->exists();

        if (! $consented) {
            return response()->json(['error' => ['code' => 'consent_required', 'message' => 'The customer has not consented to external analysis of this case.']], 403);
        }

        $application->load(['liabilities', 'latestSnapshot', 'lenderApplications', 'closures.liability']);
        $application->recordEvent('case_read_by_api_client', ['client_user' => $request->user()?->getAuthIdentifier()], $request->user()?->getAuthIdentifier());

        return (new DebtCaseResource($application))->response();
    }
}
