<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PageSeo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PageSeoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PageSeo');
    }

    public function view(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('View:PageSeo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PageSeo');
    }

    public function update(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('Update:PageSeo');
    }

    public function delete(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('Delete:PageSeo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PageSeo');
    }

    public function restore(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('Restore:PageSeo');
    }

    public function forceDelete(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('ForceDelete:PageSeo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PageSeo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PageSeo');
    }

    public function replicate(AuthUser $authUser, PageSeo $pageSeo): bool
    {
        return $authUser->can('Replicate:PageSeo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PageSeo');
    }
}
