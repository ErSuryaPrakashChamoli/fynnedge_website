<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtReport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtReport');
    }

    public function view(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('View:DebtReport');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtReport');
    }

    public function update(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('Update:DebtReport');
    }

    public function delete(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('Delete:DebtReport');
    }

    public function restore(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('Restore:DebtReport');
    }

    public function forceDelete(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('ForceDelete:DebtReport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtReport');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtReport');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtReport');
    }

    public function replicate(AuthUser $authUser, DebtReport $debtReport): bool
    {
        return $authUser->can('Replicate:DebtReport');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtReport');
    }
}
