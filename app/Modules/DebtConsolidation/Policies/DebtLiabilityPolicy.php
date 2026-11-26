<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtLiability;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtLiabilityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtLiability');
    }

    public function view(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('View:DebtLiability');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtLiability');
    }

    public function update(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('Update:DebtLiability');
    }

    public function delete(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('Delete:DebtLiability');
    }

    public function restore(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('Restore:DebtLiability');
    }

    public function forceDelete(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('ForceDelete:DebtLiability');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtLiability');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtLiability');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtLiability');
    }

    public function replicate(AuthUser $authUser, DebtLiability $debtLiability): bool
    {
        return $authUser->can('Replicate:DebtLiability');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtLiability');
    }
}
