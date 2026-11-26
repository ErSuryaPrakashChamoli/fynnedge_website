<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtApplication;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtApplication');
    }

    public function view(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('View:DebtApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtApplication');
    }

    public function update(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('Update:DebtApplication');
    }

    public function delete(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('Delete:DebtApplication');
    }

    public function restore(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('Restore:DebtApplication');
    }

    public function forceDelete(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('ForceDelete:DebtApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtApplication');
    }

    public function replicate(AuthUser $authUser, DebtApplication $debtApplication): bool
    {
        return $authUser->can('Replicate:DebtApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtApplication');
    }
}
