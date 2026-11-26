<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtLenderMatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtLenderMatch');
    }

    public function view(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('View:DebtLenderMatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtLenderMatch');
    }

    public function update(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('Update:DebtLenderMatch');
    }

    public function delete(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('Delete:DebtLenderMatch');
    }

    public function restore(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('Restore:DebtLenderMatch');
    }

    public function forceDelete(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('ForceDelete:DebtLenderMatch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtLenderMatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtLenderMatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtLenderMatch');
    }

    public function replicate(AuthUser $authUser, DebtLenderMatch $debtLenderMatch): bool
    {
        return $authUser->can('Replicate:DebtLenderMatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtLenderMatch');
    }
}
