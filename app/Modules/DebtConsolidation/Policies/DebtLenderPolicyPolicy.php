<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtLenderPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtLenderPolicyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtLenderPolicy');
    }

    public function view(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('View:DebtLenderPolicy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtLenderPolicy');
    }

    public function update(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('Update:DebtLenderPolicy');
    }

    public function delete(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('Delete:DebtLenderPolicy');
    }

    public function restore(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('Restore:DebtLenderPolicy');
    }

    public function forceDelete(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('ForceDelete:DebtLenderPolicy');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtLenderPolicy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtLenderPolicy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtLenderPolicy');
    }

    public function replicate(AuthUser $authUser, DebtLenderPolicy $debtLenderPolicy): bool
    {
        return $authUser->can('Replicate:DebtLenderPolicy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtLenderPolicy');
    }
}
