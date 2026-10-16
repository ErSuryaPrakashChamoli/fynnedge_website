<?php

use App\Http\Controllers\Api\V1\ExperianController;
use Illuminate\Support\Facades\Route;

/*
 * Experian internal API — /api/v1/experian/* (docs/experian-credit-bureau-integration.md).
 *
 * Unlike routes/api.php (public, anonymous) these endpoints act on customers'
 * credit data, so they run in the `web` group with the application's existing
 * staff login (session + CSRF) and every call is authorised by the Experian
 * permissions and the customer's own policy. There is no token auth because the
 * application has none (no Sanctum); modules inside this application should
 * call App\Modules\Experian\Services\ExperianCreditService directly instead of
 * making HTTP requests to themselves.
 *
 * Records are addressed by their UUID public_id, never a sequential id.
 */
Route::prefix('api/v1/experian')
    ->name('api.v1.experian.')
    ->middleware(['experian.json', 'auth', 'throttle:experian-api'])
    ->group(function (): void {
        Route::post('/score', [ExperianController::class, 'score'])->name('score');
        Route::post('/reports', [ExperianController::class, 'report'])->name('reports.store');
        Route::get('/reports/{experianRequest}', [ExperianController::class, 'show'])->name('reports.show');
        Route::post('/reports/{experianRequest}/pdf', [ExperianController::class, 'pdf'])->name('reports.pdf');
        Route::get('/requests/{experianRequest}/status', [ExperianController::class, 'status'])->name('requests.status');
        Route::get('/customers/{customer}/reports', [ExperianController::class, 'customerReports'])->name('customers.reports');
    });
