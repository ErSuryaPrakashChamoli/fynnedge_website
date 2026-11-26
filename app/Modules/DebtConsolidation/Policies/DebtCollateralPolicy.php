<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtCollateral;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtCollateralPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtCollateral');
    }

    public function view(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('View:DebtCollateral');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtCollateral');
    }

    public function update(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('Update:DebtCollateral');
    }

    public function delete(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('Delete:DebtCollateral');
    }

    public function restore(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('Restore:DebtCollateral');
    }

    public function forceDelete(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('ForceDelete:DebtCollateral');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtCollateral');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtCollateral');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtCollateral');
    }

    public function replicate(AuthUser $authUser, DebtCollateral $debtCollateral): bool
    {
        return $authUser->can('Replicate:DebtCollateral');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtCollateral');
    }
}
