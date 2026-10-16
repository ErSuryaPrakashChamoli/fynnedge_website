<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCibilCheckRequest;
use App\Http\Resources\Api\V1\CibilRequestResource;
use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\DataTransferObjects\CibilCheckRequest;
use App\Modules\Cibil\DataTransferObjects\CibilOutcome;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilCreditService;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CIBIL internal API (routes/cibil.php). Thin: every decision —
 * permission, consent, duplicate, reuse — is made by the CIBIL module itself
 * (CibilCreditService → RequestCibilCheck), exactly as for the admin.
 *
 * Envelope: { success, message, data | error_code }. Status codes: 202 a new
 * paid request was queued, 200 an existing result was reused / read, 409 an
 * equivalent request is already in flight, 422 invalid input or refused
 * (consent, rejected details…), 403 not permitted, 401 not signed in, 404 not
 * found, 429 rate limited.
 */
class CibilController extends Controller
{
    public function score(StoreCibilCheckRequest $request, CibilCreditService $cibil): JsonResponse
    {
        return $this->requestCheck($request, $cibil, CibilProduct::Score);
    }

    public function report(StoreCibilCheckRequest $request, CibilCreditService $cibil): JsonResponse
    {
        return $this->requestCheck($request, $cibil, CibilProduct::Report);
    }

    public function show(Request $request, CibilCreditRequest $cibilRequest): JsonResponse
    {
        $this->authorizeRequest($request, $cibilRequest);

        $includes = array_map('trim', explode(',', (string) $request->query('include', '')));

        if (in_array('report', $includes, true) && $request->user()->can('viewReport', $cibilRequest) && $cibilRequest->hasReport()) {
            $cibilRequest->recordEvent('viewed', ['channel' => 'api']);
        }

        if (in_array('raw', $includes, true) && $request->user()->can('viewRaw', $cibilRequest)) {
            $cibilRequest->recordEvent('raw_viewed', ['channel' => 'api']);
        }

        return $this->respond($cibilRequest->load(['customer', 'journeySession']), 'CIBIL request.');
    }

    public function status(Request $request, CibilCreditRequest $cibilRequest): JsonResponse
    {
        $this->authorizeRequest($request, $cibilRequest);

        return response()->json([
            'success' => true,
            'message' => 'CIBIL request status.',
            'data' => [
                'id' => $cibilRequest->public_id,
                'product' => $cibilRequest->product->value,
                'status' => $cibilRequest->status->value,
                'status_label' => $cibilRequest->status->getLabel(),
                'score' => $cibilRequest->score,
                'completed_at' => $cibilRequest->completed_at?->toIso8601String(),
                'pdf_available' => app(FetchCibilPdf::class)->isAvailable($cibilRequest),
                'error' => $cibilRequest->error_code ? ['code' => $cibilRequest->error_code->value, 'message' => $cibilRequest->error_code->safeMessage()] : null,
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
    public function pdf(Request $request, CibilCreditRequest $cibilRequest, FetchCibilPdf $fetchPdf, CibilCreditService $cibil): Response
    {
        $this->authorizeRequest($request, $cibilRequest);
        Gate::authorize('downloadPdf', $cibilRequest);
        $user = $this->user($request);

        try {
            if ($cibilRequest->product === CibilProduct::Pdf || $fetchPdf->isAvailable($cibilRequest)) {
                return $this->pdfResponse($cibilRequest, $fetchPdf->handle($cibilRequest, $user));
            }

            $outcome = $cibil->request(CibilCheckRequest::pdfFor($cibilRequest, 'api'), $user);

            if ($outcome->kind === CibilOutcome::REUSED) {
                $this->authorizeRequest($request, $outcome->request);

                return $this->pdfResponse($outcome->request, $fetchPdf->handle($outcome->request, $user));
            }
        } catch (CibilRequestRefused $exception) {
            return $this->refused($exception);
        }

        return $this->outcomeResponse($outcome);
    }

    public function customerReports(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        Gate::authorize('viewAny', CibilCreditRequest::class);

        $requests = $customer->cibilRequests()
            ->with(['customer', 'journeySession'])
            ->latest('requested_at')
            ->latest('id')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return CibilRequestResource::collection($requests)
            ->additional(['success' => true, 'message' => 'CIBIL requests for this customer.'])
            ->response();
    }

    private function requestCheck(StoreCibilCheckRequest $request, CibilCreditService $cibil, CibilProduct $product): JsonResponse
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
            $outcome = $cibil->request($request->toCheckRequest($product, $customer, $journeySession), $this->user($request));
        } catch (CibilRequestRefused $exception) {
            return $this->refused($exception);
        }

        return $this->outcomeResponse($outcome);
    }

    private function outcomeResponse(CibilOutcome $outcome): JsonResponse
    {
        $status = match ($outcome->kind) {
            CibilOutcome::QUEUED => 202,
            CibilOutcome::BLOCKED => 409,
            default => 200,
        };

        return CibilRequestResource::make($outcome->request->load(['customer', 'journeySession']))
            ->additional(['success' => $outcome->kind !== CibilOutcome::BLOCKED, 'message' => $outcome->message(), 'outcome' => $outcome->kind])
            ->response()
            ->setStatusCode($status);
    }

    /**
     * Viewing a request needs the CIBIL permission AND access to its customer,
     * so a guessed or leaked id is useless to someone outside that boundary.
     */
    private function authorizeRequest(Request $request, CibilCreditRequest $cibilRequest): void
    {
        Gate::authorize('view', $cibilRequest);

        if ($cibilRequest->customer !== null) {
            Gate::authorize('view', $cibilRequest->customer);
        }
    }

    private function pdfResponse(CibilCreditRequest $cibilRequest, string $pdf): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cibil-report-'.$cibilRequest->pan_masked.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-CIBIL-Request-Id' => $cibilRequest->public_id,
        ]);
    }

    private function respond(CibilCreditRequest $cibilRequest, string $message): JsonResponse
    {
        return CibilRequestResource::make($cibilRequest)
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }

    private function refused(CibilRequestRefused $exception): JsonResponse
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

        return $user instanceof User ? $user : throw new AuthorizationException('Sign in again to use the CIBIL API.');
    }
}
