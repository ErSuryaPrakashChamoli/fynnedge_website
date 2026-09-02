<?php

declare(strict_types=1);

namespace App\Modules\Eligibility\Policies;

use App\Modules\Eligibility\Models\Employer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmployerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Employer');
    }

    public function view(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('View:Employer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Employer');
    }

    public function update(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Update:Employer');
    }

    public function delete(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Delete:Employer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Employer');
    }

    public function restore(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Restore:Employer');
    }

    public function forceDelete(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('ForceDelete:Employer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Employer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Employer');
    }

    public function replicate(AuthUser $authUser, Employer $employer): bool
    {
        return $authUser->can('Replicate:Employer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Employer');
    }
}
