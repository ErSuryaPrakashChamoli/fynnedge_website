<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtNote;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtNote');
    }

    public function view(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('View:DebtNote');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtNote');
    }

    public function update(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('Update:DebtNote');
    }

    public function delete(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('Delete:DebtNote');
    }

    public function restore(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('Restore:DebtNote');
    }

    public function forceDelete(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('ForceDelete:DebtNote');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtNote');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtNote');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtNote');
    }

    public function replicate(AuthUser $authUser, DebtNote $debtNote): bool
    {
        return $authUser->can('Replicate:DebtNote');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtNote');
    }
}
