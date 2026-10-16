<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExperianCheckRequest;
use App\Http\Resources\Api\V1\ExperianRequestResource;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\DataTransferObjects\ExperianOutcome;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianCreditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Experian internal API (routes/experian.php). Thin: every decision —
 * permission, consent, duplicate, reuse — is made by the Experian module itself
 * (ExperianCreditService → RequestExperianCheck), exactly as for the admin.
 *
 * Envelope: { success, message, data | error_code }. Status codes: 202 a new
 * paid request was queued, 200 an existing result was reused / read, 409 an
 * equivalent request is already in flight, 422 invalid input or refused
 * (consent, rejected details…), 403 not permitted, 401 not signed in, 404 not
 * found, 429 rate limited.
 */
class ExperianController extends Controller
{
    public function score(StoreExperianCheckRequest $request, ExperianCreditService $experian): JsonResponse
    {
        return $this->requestCheck($request, $experian, ExperianProduct::Score);
    }

    public function report(StoreExperianCheckRequest $request, ExperianCreditService $experian): JsonResponse
    {
        return $this->requestCheck($request, $experian, ExperianProduct::Report);
    }

    public function show(Request $request, ExperianCreditRequest $experianRequest): JsonResponse
    {
        $this->authorizeRequest($request, $experianRequest);

        $includes = array_map('trim', explode(',', (string) $request->query('include', '')));

        if (in_array('report', $includes, true) && $request->user()->can('viewReport', $experianRequest) && $experianRequest->hasReport()) {
            $experianRequest->recordEvent('viewed', ['channel' => 'api']);
        }

        if (in_array('raw', $includes, true) && $request->user()->can('viewRaw', $experianRequest)) {
            $experianRequest->recordEvent('raw_viewed', ['channel' => 'api']);
        }

        return $this->respond($experianRequest->load(['customer', 'journeySession']), 'Experian request.');
    }

    public function status(Request $request, ExperianCreditRequest $experianRequest): JsonResponse
    {
        $this->authorizeRequest($request, $experianRequest);

        return response()->json([
            'success' => true,
            'message' => 'Experian request status.',
            'data' => [
                'id' => $experianRequest->public_id,
                'product' => $experianRequest->product->value,
                'status' => $experianRequest->status->value,
                'status_label' => $experianRequest->status->getLabel(),
                'score' => $experianRequest->score,
                'completed_at' => $experianRequest->completed_at?->toIso8601String(),
                'pdf_available' => app(FetchExperianPdf::class)->isAvailable($experianRequest),
                'error' => $experianRequest->error_code ? ['code' => $experianRequest->error_code->value, 'message' => $experianRequest->error_code->safeMessage()] : null,
            ],
        ]);
    }

    /**
     * The PDF report for this record, as application/pdf, when one is at hand:
     * the record is a PDF request whose file is stored (or still downloadable
     * from Surepass's link), or a recent stored PDF for the same PAN is reused.
     * Otherwise a paid PDF request is queued for the same person on the
     * record's consent (202 — poll its status, then call this endpoint with
     * ITS id), or refused as a duplicate of one in progress (409). Opening a
     * PDF never repeats a paid call.
     */
    public function pdf(Request $request, ExperianCreditRequest $experianRequest, FetchExperianPdf $fetchPdf, ExperianCreditService $experian): Response
    {
        $this->authorizeRequest($request, $experianRequest);
        Gate::authorize('downloadPdf', $experianRequest);
        $user = $this->user($request);

        try {
            if ($experianRequest->product === ExperianProduct::Pdf || $fetchPdf->isAvailable($experianRequest)) {
                return $this->pdfResponse($experianRequest, $fetchPdf->handle($experianRequest, $user));
            }

            $outcome = $experian->request(ExperianCheckRequest::pdfFor($experianRequest, 'api'), $user);

            if ($outcome->kind === ExperianOutcome::REUSED) {
                $this->authorizeRequest($request, $outcome->request);

                return $this->pdfResponse($outcome->request, $fetchPdf->handle($outcome->request, $user));
            }
        } catch (ExperianRequestRefused $exception) {
            return $this->refused($exception);
        }

        return $this->outcomeResponse($outcome);
    }

    public function customerReports(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        Gate::authorize('viewAny', ExperianCreditRequest::class);

        $requests = $customer->experianRequests()
            ->with(['customer', 'journeySession'])
            ->latest('requested_at')
            ->latest('id')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return ExperianRequestResource::collection($requests)
            ->additional(['success' => true, 'message' => 'Experian requests for this customer.'])
            ->response();
    }

    private function requestCheck(StoreExperianCheckRequest $request, ExperianCreditService $experian, ExperianProduct $product): JsonResponse
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
            $outcome = $experian->request($request->toCheckRequest($product, $customer, $journeySession), $this->user($request));
        } catch (ExperianRequestRefused $exception) {
            return $this->refused($exception);
        }

        return $this->outcomeResponse($outcome);
    }

    private function outcomeResponse(ExperianOutcome $outcome): JsonResponse
    {
        $status = match ($outcome->kind) {
            ExperianOutcome::QUEUED => 202,
            ExperianOutcome::BLOCKED => 409,
            default => 200,
        };

        return ExperianRequestResource::make($outcome->request->load(['customer', 'journeySession']))
            ->additional(['success' => $outcome->kind !== ExperianOutcome::BLOCKED, 'message' => $outcome->message(), 'outcome' => $outcome->kind])
            ->response()
            ->setStatusCode($status);
    }

    /**
     * Viewing a request needs the Experian permission AND access to its customer,
     * so a guessed or leaked id is useless to someone outside that boundary.
     */
    private function authorizeRequest(Request $request, ExperianCreditRequest $experianRequest): void
    {
        Gate::authorize('view', $experianRequest);

        if ($experianRequest->customer !== null) {
            Gate::authorize('view', $experianRequest->customer);
        }
    }

    private function pdfResponse(ExperianCreditRequest $experianRequest, string $pdf): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="experian-report-'.$experianRequest->pan_masked.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Experian-Request-Id' => $experianRequest->public_id,
        ]);
    }

    private function respond(ExperianCreditRequest $experianRequest, string $message): JsonResponse
    {
        return ExperianRequestResource::make($experianRequest)
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }

    private function refused(ExperianRequestRefused $exception): JsonResponse
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

        return $user instanceof User ? $user : throw new AuthorizationException('Sign in again to use the Experian API.');
    }
}
