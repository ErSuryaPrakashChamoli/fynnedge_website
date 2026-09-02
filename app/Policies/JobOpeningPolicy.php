<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobOpening;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class JobOpeningPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobOpening');
    }

    public function view(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('View:JobOpening');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobOpening');
    }

    public function update(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('Update:JobOpening');
    }

    public function delete(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('Delete:JobOpening');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JobOpening');
    }

    public function restore(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('Restore:JobOpening');
    }

    public function forceDelete(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('ForceDelete:JobOpening');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JobOpening');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JobOpening');
    }

    public function replicate(AuthUser $authUser, JobOpening $jobOpening): bool
    {
        return $authUser->can('Replicate:JobOpening');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JobOpening');
    }
}
