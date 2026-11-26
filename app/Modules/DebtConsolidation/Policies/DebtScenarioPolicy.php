<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtScenario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtScenarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtScenario');
    }

    public function view(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('View:DebtScenario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtScenario');
    }

    public function update(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('Update:DebtScenario');
    }

    public function delete(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('Delete:DebtScenario');
    }

    public function restore(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('Restore:DebtScenario');
    }

    public function forceDelete(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('ForceDelete:DebtScenario');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtScenario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtScenario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtScenario');
    }

    public function replicate(AuthUser $authUser, DebtScenario $debtScenario): bool
    {
        return $authUser->can('Replicate:DebtScenario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtScenario');
    }
}
