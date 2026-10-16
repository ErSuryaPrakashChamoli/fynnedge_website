<?php

use App\Http\Controllers\Api\V1\CibilController;
use Illuminate\Support\Facades\Route;

/*
 * CIBIL internal API — /api/v1/cibil/* (docs/cibil-credit-bureau-integration.md).
 *
 * Unlike routes/api.php (public, anonymous) these endpoints act on customers'
 * credit data, so they run in the `web` group with the application's existing
 * staff login (session + CSRF) and every call is authorised by the CIBIL
 * permissions and the customer's own policy. There is no token auth because the
 * application has none (no Sanctum); modules inside this application should
 * call App\Modules\Cibil\Services\CibilCreditService directly instead of
 * making HTTP requests to themselves.
 *
 * Records are addressed by their UUID public_id, never a sequential id.
 */
Route::prefix('api/v1/cibil')
    ->name('api.v1.cibil.')
    ->middleware(['cibil.json', 'auth', 'throttle:cibil-api'])
    ->group(function (): void {
        Route::post('/score', [CibilController::class, 'score'])->name('score');
        Route::post('/reports', [CibilController::class, 'report'])->name('reports.store');
        Route::get('/reports/{cibilRequest}', [CibilController::class, 'show'])->name('reports.show');
        Route::post('/reports/{cibilRequest}/pdf', [CibilController::class, 'pdf'])->name('reports.pdf');
        Route::get('/requests/{cibilRequest}/status', [CibilController::class, 'status'])->name('requests.status');
        Route::get('/customers/{customer}/reports', [CibilController::class, 'customerReports'])->name('customers.reports');
    });
