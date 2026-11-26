<?php

namespace App\Http\Controllers\Portal;

use App\Models\Setting;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\DebtConsolidation\Actions\RecordDebtConsent;
use App\Modules\DebtConsolidation\Actions\UploadDebtDocument;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\AdvisorReviewStatus;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Support\Pages\DebtConsolidationPages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Profile → Debt Consolidation. Every lookup starts from the signed-in
 * customer's own cases (DebtApplication::ownedBy: linked to the account AND
 * opened from its OTP-verified mobile), so another customer's case,
 * document or report answers 404. Nothing here calls a lender or a bureau.
 */
class DebtConsolidationController extends PortalController
{
    /**
     * The document types a customer may upload from the portal.
     */
    private const CUSTOMER_DOCUMENT_TYPES = ['loan_statement', 'credit_card_statement', 'foreclosure_letter', 'noc_closure_letter', 'bank_statement', 'payslip', 'itr', 'business_financials', 'gst_returns', 'other_document'];

    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('portal.debt-consolidation.index', [
            'customer' => $customer,
            'applications' => DebtApplication::query()->ownedBy($customer)->with('latestSnapshot')->latest('id')->get(),
            'assessmentUrl' => $this->assessmentUrl(),
        ]);
    }

    public function show(Request $request, string $application): View
    {
        $customer = $this->customer($request);
        $case = $this->ownedCase($request, $application)->load([
            'liabilities', 'latestSnapshot', 'assignee',
            'documents' => fn ($query) => $query->whereDoesntHave('supersededBy')->with('documentType'),
            'lenderApplications', 'closures.liability', 'closures.payments',
            'reports' => fn ($query) => $query->where('is_customer_visible', true),
            'notes' => fn ($query) => $query->where('visible_to_customer', true),
            'consents.lenderProduct.lender',
            'lenderMatches' => fn ($query) => $query->where('advisor_status', AdvisorReviewStatus::Shortlisted)->with('lenderProduct.lender', 'lenderProduct.loanProduct'),
        ]);

        return view('portal.debt-consolidation.show', [
            'customer' => $customer,
            'application' => $case,
            'snapshot' => $case->latestSnapshot,
            'documentTypes' => DocumentType::query()->whereIn('key', self::CUSTOMER_DOCUMENT_TYPES)->orderBy('order')->get(),
            'shortlisted' => ! $case->status->isOpen() ? collect() : $case->lenderMatches->reject(fn (DebtLenderMatch $match): bool => $case->consents->contains(fn (DebtConsent $consent): bool => $consent->isActive() && $consent->purpose === ConsentPurpose::LenderSharing && $consent->lender_product_id === $match->lender_product_id)),
            'assessmentUrl' => $case->isEditableByCustomer() ? $this->assessmentUrl() : null,
            'contactPhone' => Setting::get('contact_phone'),
        ]);
    }

    public function uploadDocument(Request $request, string $application, UploadDebtDocument $upload): RedirectResponse
    {
        $case = $this->ownedCase($request, $application);
        abort_unless($case->status->isOpen(), 403, __('This case is closed.'));
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('debt_consolidation.documents.max_kilobytes'), 'mimes:'.implode(',', (array) config('debt_consolidation.documents.mimes'))],
            'document_type_id' => ['required', Rule::exists('document_types', 'id')->whereIn('key', self::CUSTOMER_DOCUMENT_TYPES)],
            'debt_liability_id' => ['nullable', Rule::exists('debt_liabilities', 'id')->where('debt_application_id', $case->getKey())],
            'replaces' => ['nullable', 'uuid'],
        ]);

        $replaces = filled($validated['replaces'] ?? null) ? $case->documents()->where('public_id', $validated['replaces'])->first() : null;

        $upload->handle($case, $request->file('file'), DocumentType::query()->find($validated['document_type_id']), ActorType::Customer, liabilityId: isset($validated['debt_liability_id']) ? (int) $validated['debt_liability_id'] : null, replaces: $replaces);

        return back()->with('status', __('Document uploaded. Our team will review it.'));
    }

    public function document(Request $request, string $application, string $document): StreamedResponse
    {
        $case = $this->ownedCase($request, $application);
        $file = $case->documents()->where('public_id', $document)->first() ?? abort(404);
        $case->recordEvent('document_downloaded_by_customer', ['document' => $file->public_id]);

        return response()->streamDownload(fn () => print $file->contents(), $file->downloadName(), [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function report(Request $request, string $application, string $report): StreamedResponse
    {
        $case = $this->ownedCase($request, $application);
        $file = $case->reports()->where('public_id', $report)->where('is_customer_visible', true)->first() ?? abort(404);
        $case->recordEvent('report_downloaded_by_customer', ['report' => $file->public_id]);

        return response()->streamDownload(fn () => print $file->contents(), $file->downloadName(), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The customer's consent to share their case with ONE lender the advisor
     * shortlisted — required before FynnEdge applies to that lender.
     */
    public function consentToLender(Request $request, string $application, RecordDebtConsent $consents): RedirectResponse
    {
        $case = $this->ownedCase($request, $application);
        abort_unless($case->status->isOpen(), 403, __('This case is closed.'));
        $validated = $request->validate(['match' => ['required', 'uuid'], 'agree' => ['accepted']], ['agree.accepted' => __('Tick the box to give your consent.')]);
        $match = $case->lenderMatches()->where('public_id', $validated['match'])->where('advisor_status', AdvisorReviewStatus::Shortlisted)->with('lenderProduct.lender')->first() ?? abort(404);

        if (! $consents->hasActive($case, ConsentPurpose::LenderSharing, $match->lenderProduct)) {
            $consents->handle($case, ConsentPurpose::LenderSharing, DebtConsent::METHOD_WEB_CHECKBOX, $match->lenderProduct, $request->ip(), $request->userAgent());
        }

        return back()->with('status', __('Thank you. We can now apply to :lender for you.', ['lender' => $match->lenderProduct?->lender?->name]));
    }

    public function withdrawConsent(Request $request, string $application, string $consent, DebtCaseNotifier $notifier): RedirectResponse
    {
        $case = $this->ownedCase($request, $application);
        $record = $case->consents()->where('public_id', $consent)->whereNull('revoked_at')->first() ?? abort(404);

        $record->forceFill(['revoked_at' => now(), 'revocation_reason' => 'Withdrawn by the customer in their profile'])->save();
        $case->recordEvent('consent_withdrawn_by_customer', ['consent' => $record->public_id, 'purpose' => $record->purpose->value]);
        $notifier->consentWithdrawn($case, $record);

        return back()->with('status', __('Your consent has been withdrawn. Contact us if you have questions about information already shared.'));
    }

    public function notifications(Request $request): View
    {
        $customer = $this->customer($request);
        $notifications = $customer->notifications()->latest()->paginate(20);
        $customer->unreadNotifications()->update(['read_at' => now()]);

        return view('portal.debt-consolidation.notifications', ['customer' => $customer, 'notifications' => $notifications]);
    }

    private function ownedCase(Request $request, string $publicId): DebtApplication
    {
        return DebtApplication::query()->ownedBy($this->customer($request))->where('public_id', $publicId)->first() ?? abort(404);
    }

    private function assessmentUrl(): ?string
    {
        return DebtConsolidationPages::liveUrl('assessment');
    }
}
