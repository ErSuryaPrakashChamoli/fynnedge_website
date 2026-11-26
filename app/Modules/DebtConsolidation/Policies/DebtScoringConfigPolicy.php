<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtScoringConfig;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtScoringConfigPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtScoringConfig');
    }

    public function view(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('View:DebtScoringConfig');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtScoringConfig');
    }

    public function update(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('Update:DebtScoringConfig');
    }

    public function delete(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('Delete:DebtScoringConfig');
    }

    public function restore(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('Restore:DebtScoringConfig');
    }

    public function forceDelete(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('ForceDelete:DebtScoringConfig');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtScoringConfig');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtScoringConfig');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtScoringConfig');
    }

    public function replicate(AuthUser $authUser, DebtScoringConfig $debtScoringConfig): bool
    {
        return $authUser->can('Replicate:DebtScoringConfig');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtScoringConfig');
    }
}
