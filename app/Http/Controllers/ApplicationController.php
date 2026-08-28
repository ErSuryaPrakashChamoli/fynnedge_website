<?php

namespace App\Http\Controllers;

use App\Modules\Applications\Actions\SelectLenderForApplication;
use App\Modules\Applications\Actions\SubmitApplication;
use App\Modules\Applications\Actions\UploadApplicationDocument;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Eligibility\Models\EligibilityResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $uploadedByType = $application->documents->keyBy('document_type_id');

        return view('applications.show', [
            'application' => $application,
            'requirements' => $application->requiredDocuments(),
            'uploadedByType' => $uploadedByType,
            'allUploaded' => $application->hasAllRequiredDocuments(),
        ]);
    }

    public function uploadDocuments(Application $application, Request $request, UploadApplicationDocument $action): RedirectResponse
    {
        $requiredTypeIds = $application->requiredDocuments()->pluck('document_type_id')->all();

        $validated = $request->validate([
            'documents' => ['array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        foreach ($validated['documents'] ?? [] as $documentTypeId => $file) {
            if (! $file || ! in_array((int) $documentTypeId, $requiredTypeIds, true)) {
                continue;
            }

            $documentType = DocumentType::query()->findOrFail($documentTypeId);
            $action->handle($application, $documentType, $file);
        }

        return redirect()->route('applications.show', $application)->with('status', 'Documents uploaded.');
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
