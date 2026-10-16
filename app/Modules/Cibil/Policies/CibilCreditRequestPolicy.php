<?php

declare(strict_types=1);

namespace App\Modules\Cibil\Policies;

use App\Modules\Cibil\Models\CibilCreditRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shield-style permissions for CIBIL (see App\Modules\Cibil\Services\CibilRoles
 * for who gets what). The same policy guards the admin screens, the internal
 * API and CibilCreditService. There is no update/delete: a request is a
 * billing and audit record, changed only by the workflow actions and purged
 * only by the retention command.
 */
class CibilCreditRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CibilCreditRequest');
    }

    /** The request, its status and score — not the report contents. */
    public function view(AuthUser $authUser, CibilCreditRequest $cibilCreditRequest): bool
    {
        return $authUser->can('View:CibilCreditRequest');
    }

    /** The full report contents (accounts, identities, addresses, enquiries). */
    public function viewReport(AuthUser $authUser, ?CibilCreditRequest $cibilCreditRequest = null): bool
    {
        return $authUser->can('ViewReport:CibilCreditRequest');
    }

    /** Request a score, report or PDF (reusing a recent one) or retry a failed attempt. */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CibilCreditRequest');
    }

    public function update(AuthUser $authUser, CibilCreditRequest $cibilCreditRequest): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, CibilCreditRequest $cibilCreditRequest): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    /** Pay again although a reusable result exists — always with a recorded reason. */
    public function fetchFresh(AuthUser $authUser): bool
    {
        return $authUser->can('FetchFresh:CibilCreditRequest');
    }

    public function reconcile(AuthUser $authUser, ?CibilCreditRequest $cibilCreditRequest = null): bool
    {
        return $authUser->can('Reconcile:CibilCreditRequest');
    }

    /** The complete provider response, including unmasked identity numbers. */
    public function viewRaw(AuthUser $authUser, ?CibilCreditRequest $cibilCreditRequest = null): bool
    {
        return $authUser->can('ViewRaw:CibilCreditRequest');
    }

    public function downloadPdf(AuthUser $authUser, ?CibilCreditRequest $cibilCreditRequest = null): bool
    {
        return $authUser->can('DownloadPdf:CibilCreditRequest');
    }
}
