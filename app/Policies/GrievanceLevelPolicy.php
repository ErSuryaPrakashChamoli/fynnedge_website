<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GrievanceLevel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GrievanceLevelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GrievanceLevel');
    }

    public function view(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('View:GrievanceLevel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GrievanceLevel');
    }

    public function update(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('Update:GrievanceLevel');
    }

    public function delete(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('Delete:GrievanceLevel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GrievanceLevel');
    }

    public function restore(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('Restore:GrievanceLevel');
    }

    public function forceDelete(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('ForceDelete:GrievanceLevel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GrievanceLevel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GrievanceLevel');
    }

    public function replicate(AuthUser $authUser, GrievanceLevel $grievanceLevel): bool
    {
        return $authUser->can('Replicate:GrievanceLevel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GrievanceLevel');
    }
}
