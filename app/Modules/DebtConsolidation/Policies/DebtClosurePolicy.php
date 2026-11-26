<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtClosure;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtClosurePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtClosure');
    }

    public function view(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('View:DebtClosure');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtClosure');
    }

    public function update(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('Update:DebtClosure');
    }

    public function delete(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('Delete:DebtClosure');
    }

    public function restore(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('Restore:DebtClosure');
    }

    public function forceDelete(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('ForceDelete:DebtClosure');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtClosure');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtClosure');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtClosure');
    }

    public function replicate(AuthUser $authUser, DebtClosure $debtClosure): bool
    {
        return $authUser->can('Replicate:DebtClosure');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtClosure');
    }
}
