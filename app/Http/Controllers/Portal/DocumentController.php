<?php

namespace App\Http\Controllers\Portal;

use App\Modules\Customers\Services\CustomerApplications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Documents: the files already uploaded to the customer's own loan
 * applications (application_documents, private `local` disk). Uploading stays
 * on each application's page, which validates type and size; this page lists
 * what is there, what is still missing, and serves the customer's own files.
 */
class DocumentController extends PortalController
{
    public function index(Request $request, CustomerApplications $applications): View
    {
        $customer = $this->customer($request);
        $owned = $applications->applications($customer)->get();

        return view('portal.documents', [
            'customer' => $customer,
            'documents' => $applications->documents($customer)->get(),
            'applications' => $owned,
            'missingDocuments' => $applications->missingDocumentCounts($owned),
        ]);
    }

    public function show(Request $request, string $document, CustomerApplications $applications): StreamedResponse
    {
        $customer = $this->customer($request);
        $file = $applications->documents($customer)->where('public_id', $document)->first() ?? abort(404);

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        $customer->recordEvent('document_downloaded', ['document' => $file->public_id]);

        // Always an attachment: the stored type came from the upload, so the
        // file is never rendered inline in the site's origin.
        return Storage::disk($file->disk)->download($file->path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
