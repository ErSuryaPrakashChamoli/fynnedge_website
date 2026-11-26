<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtDocument;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtDocument');
    }

    public function view(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('View:DebtDocument');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtDocument');
    }

    public function update(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('Update:DebtDocument');
    }

    public function delete(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('Delete:DebtDocument');
    }

    public function restore(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('Restore:DebtDocument');
    }

    public function forceDelete(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('ForceDelete:DebtDocument');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtDocument');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtDocument');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtDocument');
    }

    public function replicate(AuthUser $authUser, DebtDocument $debtDocument): bool
    {
        return $authUser->can('Replicate:DebtDocument');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtDocument');
    }
}
