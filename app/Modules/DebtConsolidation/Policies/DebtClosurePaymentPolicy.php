<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtClosurePayment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Closure payments are part of a closure: managing them needs Update on the closure.
 */
class DebtClosurePaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtClosure');
    }

    public function view(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
    {
        return $authUser->can('View:DebtClosure');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:DebtClosure');
    }

    public function update(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
    {
        return $authUser->can('Update:DebtClosure');
    }

    public function delete(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
    {
        return $authUser->can('Update:DebtClosure');
    }

    public function restore(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
    {
        return $authUser->can('Restore:DebtClosure');
    }

    public function forceDelete(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
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

    public function replicate(AuthUser $authUser, DebtClosurePayment $debtClosurePayment): bool
    {
        return $authUser->can('Replicate:DebtClosure');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtClosure');
    }
}
