<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtConsent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtConsentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtConsent');
    }

    public function view(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('View:DebtConsent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtConsent');
    }

    public function update(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('Update:DebtConsent');
    }

    public function delete(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('Delete:DebtConsent');
    }

    public function restore(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('Restore:DebtConsent');
    }

    public function forceDelete(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('ForceDelete:DebtConsent');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtConsent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtConsent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtConsent');
    }

    public function replicate(AuthUser $authUser, DebtConsent $debtConsent): bool
    {
        return $authUser->can('Replicate:DebtConsent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtConsent');
    }
}
