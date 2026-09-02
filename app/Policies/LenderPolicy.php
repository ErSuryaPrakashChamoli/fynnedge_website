<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lender;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LenderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Lender');
    }

    public function view(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('View:Lender');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Lender');
    }

    public function update(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('Update:Lender');
    }

    public function delete(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('Delete:Lender');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Lender');
    }

    public function restore(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('Restore:Lender');
    }

    public function forceDelete(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('ForceDelete:Lender');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Lender');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Lender');
    }

    public function replicate(AuthUser $authUser, Lender $lender): bool
    {
        return $authUser->can('Replicate:Lender');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Lender');
    }
}
