<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Models\Application;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Application');
    }

    public function view(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('View:Application');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Application');
    }

    public function update(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Update:Application');
    }

    public function delete(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Delete:Application');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Application');
    }

    public function restore(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Restore:Application');
    }

    public function forceDelete(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('ForceDelete:Application');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Application');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Application');
    }

    public function replicate(AuthUser $authUser, Application $application): bool
    {
        return $authUser->can('Replicate:Application');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Application');
    }
}
