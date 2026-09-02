<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NavigationLink;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NavigationLinkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NavigationLink');
    }

    public function view(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('View:NavigationLink');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NavigationLink');
    }

    public function update(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('Update:NavigationLink');
    }

    public function delete(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('Delete:NavigationLink');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NavigationLink');
    }

    public function restore(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('Restore:NavigationLink');
    }

    public function forceDelete(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('ForceDelete:NavigationLink');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NavigationLink');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NavigationLink');
    }

    public function replicate(AuthUser $authUser, NavigationLink $navigationLink): bool
    {
        return $authUser->can('Replicate:NavigationLink');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NavigationLink');
    }
}
