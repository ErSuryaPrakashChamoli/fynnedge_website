<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtCalculationSnapshotPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtCalculationSnapshot');
    }

    public function view(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('View:DebtCalculationSnapshot');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtCalculationSnapshot');
    }

    public function update(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('Update:DebtCalculationSnapshot');
    }

    public function delete(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('Delete:DebtCalculationSnapshot');
    }

    public function restore(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('Restore:DebtCalculationSnapshot');
    }

    public function forceDelete(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('ForceDelete:DebtCalculationSnapshot');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtCalculationSnapshot');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtCalculationSnapshot');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtCalculationSnapshot');
    }

    public function replicate(AuthUser $authUser, DebtCalculationSnapshot $debtCalculationSnapshot): bool
    {
        return $authUser->can('Replicate:DebtCalculationSnapshot');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtCalculationSnapshot');
    }
}
