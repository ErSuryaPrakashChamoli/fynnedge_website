<?php

namespace App\Http\Controllers;

use App\Modules\Applications\Actions\DeleteApplicationDocument;
use App\Modules\Applications\Actions\SelectLenderForApplication;
use App\Modules\Applications\Actions\SetApplicationAssistancePreference;
use App\Modules\Applications\Actions\SubmitApplication;
use App\Modules\Applications\Actions\UploadApplicationDocument;
use App\Modules\Applications\Enums\AssistancePreference;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Eligibility\Models\EligibilityResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function select(EligibilityResult $eligibilityResult, SelectLenderForApplication $action): RedirectResponse
    {
        $application = $action->handle($eligibilityResult);

        if (! $application) {
            return back()->with('status', 'This lender is not currently available for that profile.');
        }

        return redirect()->route('applications.show', $application);
    }

    public function show(Application $application): View
    {
        $application->load(['lenderProduct.lender', 'lenderProduct.loanProduct', 'documents.documentType']);

        $uploadedByType = $application->documents->groupBy('document_type_id');

        return view('applications.show', [
            'application' => $application,
            'requirements' => $application->documentRequirements(),
            'uploadedByType' => $uploadedByType,
            'allUploaded' => $application->hasAllRequiredDocuments(),
        ]);
    }

    public function chooseAssistance(Application $application, Request $request, SetApplicationAssistancePreference $action): RedirectResponse
    {
        $validated = $request->validate([
            'assistance_preference' => ['required', Rule::enum(AssistancePreference::class)],
        ]);

        $action->handle($application, AssistancePreference::from($validated['assistance_preference']));

        return redirect()->route('applications.show', $application);
    }

    public function uploadDocuments(Application $application, Request $request, UploadApplicationDocument $action): RedirectResponse
    {
        $requirementsByTypeId = $application->documentRequirements()->keyBy('document_type_id');

        $validated = $request->validate([
            'documents' => ['array'],
            'documents.*' => ['array'],
            'documents.*.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_labels' => ['array'],
            'document_labels.*' => ['array'],
            'document_labels.*.*' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedCount = 0;

        foreach ($validated['documents'] ?? [] as $documentTypeId => $slots) {
            $requirement = $requirementsByTypeId->get((int) $documentTypeId);

            if (! $requirement) {
                continue;
            }

            foreach ($slots as $slot => $file) {
                if (! $file) {
                    continue;
                }

                $customLabel = $requirement->documentType->allow_custom_label
                    ? ($validated['document_labels'][$documentTypeId][$slot] ?? null)
                    : null;

                $action->handle($application, $requirement->documentType, $file, (int) $slot, $customLabel);
                $uploadedCount++;
            }
        }

        if ($uploadedCount === 0) {
            return back()->withErrors(['documents' => 'Choose at least one file before uploading.']);
        }

        return redirect()->route('applications.show', $application)->with('status', 'Documents uploaded.');
    }

    /**
     * Only lets a customer remove documents of a type that allows multiple
     * uploads (e.g. "Other") — required single-slot documents (PAN, Aadhaar,
     * ...) can only be replaced by re-uploading, never removed outright.
     */
    public function deleteDocument(Application $application, ApplicationDocument $document, DeleteApplicationDocument $action): RedirectResponse
    {
        abort_unless($document->application_id === $application->id, 404);
        abort_unless($document->documentType->allow_multiple, 404);

        $action->handle($document);

        return redirect()->route('applications.show', $application)->with('status', 'Document removed.');
    }

    public function submit(Application $application, SubmitApplication $action): RedirectResponse
    {
        $errors = $action->handle($application);

        if ($errors !== []) {
            return back()->with('status', $errors[0]);
        }

        return redirect()->route('applications.show', $application)->with('status', 'Application submitted — we\'ll be in touch shortly.');
    }
}
