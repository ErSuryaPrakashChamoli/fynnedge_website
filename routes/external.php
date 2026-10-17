<?php

use App\Http\Controllers\Api\V1\CibilController;
use Illuminate\Support\Facades\Route;

/*
 * The external API — /api/v1/external/* (docs/cibil-credit-bureau-integration.md §9a) —
 * for our other systems, such as the LMS, calling FynnEdge server to server.
 *
 * Stateless (`api` group: no session, no CSRF). Every call carries an API key
 * issued under Admin → Access Control → API Clients, as
 * `Authorization: Bearer fyn_…`. The key signs in as that client's service
 * account, so its role decides what it may do, and every CIBIL rule — consent,
 * in-flight block, reuse, rejected details — applies exactly as for staff.
 *
 * The caller sends the person's details instead of a FynnEdge customer id; the
 * customer is matched by mobile, or created (MatchCustomerForBureauRequest).
 * Reading a request reuses the internal API's actions, so the answers have the
 * same shape. Records are addressed by their UUID public_id.
 */
Route::prefix('api/v1/external/cibil')
    ->name('api.v1.external.cibil.')
    ->middleware(['cibil.json', 'auth:api-client', 'throttle:cibil-external-api'])
    ->group(function (): void {
        Route::post('/score', [CibilController::class, 'externalScore'])->name('score');
        Route::post('/reports', [CibilController::class, 'externalReport'])->name('reports.store');
        Route::get('/reports/{cibilRequest}', [CibilController::class, 'show'])->name('reports.show');
        Route::post('/reports/{cibilRequest}/pdf', [CibilController::class, 'pdf'])->name('reports.pdf');
        Route::get('/requests/{cibilRequest}/status', [CibilController::class, 'status'])->name('requests.status');
    });
