<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SocialVideo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SocialVideoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SocialVideo');
    }

    public function view(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('View:SocialVideo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SocialVideo');
    }

    public function update(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('Update:SocialVideo');
    }

    public function delete(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('Delete:SocialVideo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SocialVideo');
    }

    public function restore(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('Restore:SocialVideo');
    }

    public function forceDelete(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('ForceDelete:SocialVideo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SocialVideo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SocialVideo');
    }

    public function replicate(AuthUser $authUser, SocialVideo $socialVideo): bool
    {
        return $authUser->can('Replicate:SocialVideo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SocialVideo');
    }
}
