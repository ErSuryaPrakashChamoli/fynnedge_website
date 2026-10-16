<?php

use App\Http\Controllers\Api\V1\EquifaxController;
use Illuminate\Support\Facades\Route;

/*
 * Equifax internal API — /api/v1/equifax/* (docs/equifax-credit-bureau-integration.md).
 *
 * Unlike routes/api.php (public, anonymous) these endpoints act on customers'
 * credit data, so they run in the `web` group with the application's existing
 * staff login (session + CSRF) and every call is authorised by the Equifax
 * permissions and the customer's own policy. There is no token auth because the
 * application has none (no Sanctum); modules inside this application should
 * call App\Modules\Equifax\Services\EquifaxCreditService directly instead of
 * making HTTP requests to themselves.
 *
 * Records are addressed by their UUID public_id, never a sequential id.
 */
Route::prefix('api/v1/equifax')
    ->name('api.v1.equifax.')
    ->middleware(['equifax.json', 'auth', 'throttle:equifax-api'])
    ->group(function (): void {
        Route::post('/score', [EquifaxController::class, 'score'])->name('score');
        Route::post('/reports', [EquifaxController::class, 'report'])->name('reports.store');
        Route::get('/reports/{equifaxRequest}', [EquifaxController::class, 'show'])->name('reports.show');
        Route::post('/reports/{equifaxRequest}/pdf', [EquifaxController::class, 'pdf'])->name('reports.pdf');
        Route::get('/requests/{equifaxRequest}/status', [EquifaxController::class, 'status'])->name('requests.status');
        Route::get('/customers/{customer}/reports', [EquifaxController::class, 'customerReports'])->name('customers.reports');
    });
