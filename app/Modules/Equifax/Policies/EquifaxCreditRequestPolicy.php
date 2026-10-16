<?php

declare(strict_types=1);

namespace App\Modules\Equifax\Policies;

use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shield-style permissions for Equifax (see App\Modules\Equifax\Services\EquifaxRoles
 * for who gets what). The same policy guards the admin screens, the internal
 * API and EquifaxCreditService. There is no update/delete: a request is a
 * billing and audit record, changed only by the workflow actions and purged
 * only by the retention command.
 */
class EquifaxCreditRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EquifaxCreditRequest');
    }

    /** The request, its status and score — not the report contents. */
    public function view(AuthUser $authUser, EquifaxCreditRequest $equifaxCreditRequest): bool
    {
        return $authUser->can('View:EquifaxCreditRequest');
    }

    /** The full report contents (accounts, identities, addresses, enquiries). */
    public function viewReport(AuthUser $authUser, ?EquifaxCreditRequest $equifaxCreditRequest = null): bool
    {
        return $authUser->can('ViewReport:EquifaxCreditRequest');
    }

    /** Request a score or report (reusing a recent one) or retry a failed attempt. */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EquifaxCreditRequest');
    }

    public function update(AuthUser $authUser, EquifaxCreditRequest $equifaxCreditRequest): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, EquifaxCreditRequest $equifaxCreditRequest): bool
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
        return $authUser->can('FetchFresh:EquifaxCreditRequest');
    }

    public function reconcile(AuthUser $authUser, ?EquifaxCreditRequest $equifaxCreditRequest = null): bool
    {
        return $authUser->can('Reconcile:EquifaxCreditRequest');
    }

    /** The complete provider response, including unmasked identity numbers. */
    public function viewRaw(AuthUser $authUser, ?EquifaxCreditRequest $equifaxCreditRequest = null): bool
    {
        return $authUser->can('ViewRaw:EquifaxCreditRequest');
    }

    public function downloadPdf(AuthUser $authUser, ?EquifaxCreditRequest $equifaxCreditRequest = null): bool
    {
        return $authUser->can('DownloadPdf:EquifaxCreditRequest');
    }
}
