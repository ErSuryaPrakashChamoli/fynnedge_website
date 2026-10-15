<?php

use App\Models\User;
use App\Modules\CreditBureau\Actions\ReconcileCrifReport;
use App\Modules\CreditBureau\Enums\CrifReconciliationResolution;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifRoles;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    CrifRoles::grant();
    $this->reconciler = User::factory()->create();
    $this->reconciler->givePermissionTo('Reconcile:CrifReport');
});

it('closes an uncertain attempt, releases the PAN and records who decided and why', function (CrifReconciliationResolution $resolution, bool $retryable) {
    $report = CrifReport::factory()->awaitingReconciliation()->create();

    app(ReconcileCrifReport::class)->handle($report, $resolution, 'Checked Surepass usage log for 15 Oct', $this->reconciler);

    expect($report->refresh())
        ->status->toBe(CrifReportStatus::Failed)
        ->in_flight_key->toBeNull()
        ->retryable->toBe($retryable)
        ->reconciliation_resolution->toBe($resolution)
        ->reconciliation_note->toBe('Checked Surepass usage log for 15 Oct')
        ->reconciled_by->toBe($this->reconciler->id)
        ->and($report->auditLogs()->where('action', 'updated')->first()->changes)
        ->toHaveKey('reconciliation_note');
})->with([
    'not processed' => [CrifReconciliationResolution::NotProcessed, true],
    'processed and charged' => [CrifReconciliationResolution::ProcessedCharged, false],
]);

it('refuses without the permission, without a real note, or when nothing is uncertain', function (Closure $report, ?string $permission, string $note, string $exception) {
    $user = User::factory()->create()->assignRole(CrifRoles::OFFICER_ROLE);

    if ($permission) {
        $user->givePermissionTo($permission);
    }

    expect(fn () => app(ReconcileCrifReport::class)->handle($report(), CrifReconciliationResolution::NotProcessed, $note, $user))
        ->toThrow($exception);
})->with([
    'credit officer' => [fn () => CrifReport::factory()->awaitingReconciliation()->create(), null, 'Checked the Surepass dashboard', AuthorizationException::class],
    'note too short' => [fn () => CrifReport::factory()->awaitingReconciliation()->create(), 'Reconcile:CrifReport', 'ok', CrifRequestRefused::class],
    'already settled' => [fn () => CrifReport::factory()->successful()->create(), 'Reconcile:CrifReport', 'Checked the Surepass dashboard', CrifRequestRefused::class],
]);
