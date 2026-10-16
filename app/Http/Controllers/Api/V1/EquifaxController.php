<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEquifaxCheckRequest;
use App\Http\Resources\Api\V1\EquifaxRequestResource;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Actions\FetchEquifaxPdf;
use App\Modules\Equifax\DataTransferObjects\EquifaxOutcome;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxCreditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Equifax internal API (routes/equifax.php). Thin: every decision —
 * permission, consent, duplicate, reuse — is made by the Equifax module itself
 * (EquifaxCreditService → RequestEquifaxCheck), exactly as for the admin.
 *
 * Envelope: { success, message, data | error_code }. Status codes: 202 a new
 * paid request was queued, 200 an existing result was reused / read, 409 an
 * equivalent request is already in flight, 422 invalid input or refused
 * (consent, rejected details…), 403 not permitted, 401 not signed in, 404 not
 * found, 429 rate limited.
 */
class EquifaxController extends Controller
{
    public function score(StoreEquifaxCheckRequest $request, EquifaxCreditService $equifax): JsonResponse
    {
        return $this->requestCheck($request, $equifax, EquifaxProduct::Score);
    }

    public function report(StoreEquifaxCheckRequest $request, EquifaxCreditService $equifax): JsonResponse
    {
        return $this->requestCheck($request, $equifax, EquifaxProduct::Report);
    }

    public function show(Request $request, EquifaxCreditRequest $equifaxRequest): JsonResponse
    {
        $this->authorizeRequest($request, $equifaxRequest);

        $includes = array_map('trim', explode(',', (string) $request->query('include', '')));

        if (in_array('report', $includes, true) && $request->user()->can('viewReport', $equifaxRequest) && $equifaxRequest->hasReport()) {
            $equifaxRequest->recordEvent('viewed', ['channel' => 'api']);
        }

        if (in_array('raw', $includes, true) && $request->user()->can('viewRaw', $equifaxRequest)) {
            $equifaxRequest->recordEvent('raw_viewed', ['channel' => 'api']);
        }

        return $this->respond($equifaxRequest->load(['customer', 'journeySession']), 'Equifax request.');
    }

    public function status(Request $request, EquifaxCreditRequest $equifaxRequest): JsonResponse
    {
        $this->authorizeRequest($request, $equifaxRequest);

        return response()->json([
            'success' => true,
            'message' => 'Equifax request status.',
            'data' => [
                'id' => $equifaxRequest->public_id,
                'product' => $equifaxRequest->product->value,
                'status' => $equifaxRequest->status->value,
                'status_label' => $equifaxRequest->status->getLabel(),
                'score' => $equifaxRequest->score,
                'completed_at' => $equifaxRequest->completed_at?->toIso8601String(),
                'error' => $equifaxRequest->error_code ? ['code' => $equifaxRequest->error_code->value, 'message' => $equifaxRequest->error_code->safeMessage()] : null,
            ],
        ]);
    }

    public function pdf(Request $request, EquifaxCreditRequest $equifaxRequest, FetchEquifaxPdf $fetchPdf): Response
    {
        $this->authorizeRequest($request, $equifaxRequest);

        try {
            $pdf = $fetchPdf->handle($equifaxRequest, $this->user($request));
        } catch (EquifaxRequestRefused $exception) {
            return $this->refused($exception);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="equifax-report-'.$equifaxRequest->pan_masked.'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function customerReports(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        Gate::authorize('viewAny', EquifaxCreditRequest::class);

        $requests = $customer->equifaxRequests()
            ->with(['customer', 'journeySession'])
            ->latest('requested_at')
            ->latest('id')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return EquifaxRequestResource::collection($requests)
            ->additional(['success' => true, 'message' => 'Equifax requests for this customer.'])
            ->response();
    }

    private function requestCheck(StoreEquifaxCheckRequest $request, EquifaxCreditService $equifax, EquifaxProduct $product): JsonResponse
    {
        $customer = $request->customer();
        Gate::authorize('view', $customer);

        $journeySession = $request->journeySession();

        if ($journeySession !== null && $journeySession->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'That application does not belong to this customer.',
                'error_code' => 'application_mismatch',
            ], 422);
        }

        try {
            $outcome = $equifax->request($request->toCheckRequest($product, $customer, $journeySession), $this->user($request));
        } catch (EquifaxRequestRefused $exception) {
            return $this->refused($exception);
        }

        $status = match ($outcome->kind) {
            EquifaxOutcome::QUEUED => 202,
            EquifaxOutcome::BLOCKED => 409,
            default => 200,
        };

        return EquifaxRequestResource::make($outcome->request->load(['customer', 'journeySession']))
            ->additional(['success' => $outcome->kind !== EquifaxOutcome::BLOCKED, 'message' => $outcome->message(), 'outcome' => $outcome->kind])
            ->response()
            ->setStatusCode($status);
    }

    /**
     * Viewing a request needs the Equifax permission AND access to its customer,
     * so a guessed or leaked id is useless to someone outside that boundary.
     */
    private function authorizeRequest(Request $request, EquifaxCreditRequest $equifaxRequest): void
    {
        Gate::authorize('view', $equifaxRequest);

        if ($equifaxRequest->customer !== null) {
            Gate::authorize('view', $equifaxRequest->customer);
        }
    }

    private function respond(EquifaxCreditRequest $equifaxRequest, string $message): JsonResponse
    {
        return EquifaxRequestResource::make($equifaxRequest)
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }

    private function refused(EquifaxRequestRefused $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'error_code' => 'request_refused',
        ], 422);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthorizationException('Sign in again to use the Equifax API.');
    }
}
