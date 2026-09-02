<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LenderProduct;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LenderProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LenderProduct');
    }

    public function view(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('View:LenderProduct');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LenderProduct');
    }

    public function update(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('Update:LenderProduct');
    }

    public function delete(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('Delete:LenderProduct');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LenderProduct');
    }

    public function restore(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('Restore:LenderProduct');
    }

    public function forceDelete(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('ForceDelete:LenderProduct');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LenderProduct');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LenderProduct');
    }

    public function replicate(AuthUser $authUser, LenderProduct $lenderProduct): bool
    {
        return $authUser->can('Replicate:LenderProduct');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LenderProduct');
    }
}
