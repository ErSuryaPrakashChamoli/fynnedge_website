<?php

declare(strict_types=1);

namespace App\Modules\CreditBureau\Policies;

use App\Modules\CreditBureau\Models\CrifReport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shield-style permissions for CRIF reports (see App\Modules\CreditBureau\Services\CrifRoles
 * for who gets what by default). There is deliberately no update/delete: a
 * report attempt is a billing and audit record, changed only by the workflow
 * actions and purged only by the retention command.
 */
class CrifReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CrifReport');
    }

    public function view(AuthUser $authUser, CrifReport $crifReport): bool
    {
        return $authUser->can('View:CrifReport');
    }

    /** Request a report (reusing a recent one when it exists) or retry a failed attempt. */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CrifReport');
    }

    public function update(AuthUser $authUser, CrifReport $crifReport): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, CrifReport $crifReport): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    /** Pay for a new report although a reusable one exists — always with a recorded reason. */
    public function fetchFresh(AuthUser $authUser): bool
    {
        return $authUser->can('FetchFresh:CrifReport');
    }

    /** Close an uncertain attempt after checking it against the Surepass dashboard. */
    public function reconcile(AuthUser $authUser, ?CrifReport $crifReport = null): bool
    {
        return $authUser->can('Reconcile:CrifReport');
    }

    /** See the complete provider response, including unmasked identity numbers. */
    public function viewRaw(AuthUser $authUser, ?CrifReport $crifReport = null): bool
    {
        return $authUser->can('ViewRaw:CrifReport');
    }

    public function downloadPdf(AuthUser $authUser, ?CrifReport $crifReport = null): bool
    {
        return $authUser->can('DownloadPdf:CrifReport');
    }
}
