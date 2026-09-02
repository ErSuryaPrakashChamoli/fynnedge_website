<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CompanyPhoto;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CompanyPhotoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CompanyPhoto');
    }

    public function view(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('View:CompanyPhoto');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CompanyPhoto');
    }

    public function update(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('Update:CompanyPhoto');
    }

    public function delete(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('Delete:CompanyPhoto');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CompanyPhoto');
    }

    public function restore(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('Restore:CompanyPhoto');
    }

    public function forceDelete(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('ForceDelete:CompanyPhoto');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CompanyPhoto');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CompanyPhoto');
    }

    public function replicate(AuthUser $authUser, CompanyPhoto $companyPhoto): bool
    {
        return $authUser->can('Replicate:CompanyPhoto');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CompanyPhoto');
    }
}
