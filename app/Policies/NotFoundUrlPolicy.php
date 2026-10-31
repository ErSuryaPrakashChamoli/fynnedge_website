<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotFoundUrl;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotFoundUrlPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotFoundUrl');
    }

    public function view(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('View:NotFoundUrl');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotFoundUrl');
    }

    public function update(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('Update:NotFoundUrl');
    }

    public function delete(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('Delete:NotFoundUrl');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NotFoundUrl');
    }

    public function restore(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('Restore:NotFoundUrl');
    }

    public function forceDelete(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('ForceDelete:NotFoundUrl');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotFoundUrl');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotFoundUrl');
    }

    public function replicate(AuthUser $authUser, NotFoundUrl $notFoundUrl): bool
    {
        return $authUser->can('Replicate:NotFoundUrl');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotFoundUrl');
    }
}
