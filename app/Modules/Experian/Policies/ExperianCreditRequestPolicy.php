<?php

declare(strict_types=1);

namespace App\Modules\Experian\Policies;

use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shield-style permissions for Experian (see App\Modules\Experian\Services\ExperianRoles
 * for who gets what). The same policy guards the admin screens, the internal
 * API and ExperianCreditService. There is no update/delete: a request is a
 * billing and audit record, changed only by the workflow actions and purged
 * only by the retention command.
 */
class ExperianCreditRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExperianCreditRequest');
    }

    /** The request, its status and score — not the report contents. */
    public function view(AuthUser $authUser, ExperianCreditRequest $experianCreditRequest): bool
    {
        return $authUser->can('View:ExperianCreditRequest');
    }

    /** The full report contents (accounts, identities, addresses, enquiries). */
    public function viewReport(AuthUser $authUser, ?ExperianCreditRequest $experianCreditRequest = null): bool
    {
        return $authUser->can('ViewReport:ExperianCreditRequest');
    }

    /** Request a score, report or PDF (reusing a recent one) or retry a failed attempt. */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExperianCreditRequest');
    }

    public function update(AuthUser $authUser, ExperianCreditRequest $experianCreditRequest): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, ExperianCreditRequest $experianCreditRequest): bool
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
        return $authUser->can('FetchFresh:ExperianCreditRequest');
    }

    public function reconcile(AuthUser $authUser, ?ExperianCreditRequest $experianCreditRequest = null): bool
    {
        return $authUser->can('Reconcile:ExperianCreditRequest');
    }

    /** The complete provider response, including unmasked identity numbers. */
    public function viewRaw(AuthUser $authUser, ?ExperianCreditRequest $experianCreditRequest = null): bool
    {
        return $authUser->can('ViewRaw:ExperianCreditRequest');
    }

    public function downloadPdf(AuthUser $authUser, ?ExperianCreditRequest $experianCreditRequest = null): bool
    {
        return $authUser->can('DownloadPdf:ExperianCreditRequest');
    }
}
