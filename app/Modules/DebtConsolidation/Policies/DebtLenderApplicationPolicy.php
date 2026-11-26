<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtLenderApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtLenderApplication');
    }

    public function view(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('View:DebtLenderApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtLenderApplication');
    }

    public function update(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('Update:DebtLenderApplication');
    }

    public function delete(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('Delete:DebtLenderApplication');
    }

    public function restore(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('Restore:DebtLenderApplication');
    }

    public function forceDelete(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('ForceDelete:DebtLenderApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtLenderApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtLenderApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtLenderApplication');
    }

    public function replicate(AuthUser $authUser, DebtLenderApplication $debtLenderApplication): bool
    {
        return $authUser->can('Replicate:DebtLenderApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtLenderApplication');
    }
}
