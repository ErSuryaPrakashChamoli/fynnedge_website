<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LenderProductDocumentRequirementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LenderProductDocumentRequirement');
    }

    public function view(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('View:LenderProductDocumentRequirement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LenderProductDocumentRequirement');
    }

    public function update(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('Update:LenderProductDocumentRequirement');
    }

    public function delete(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('Delete:LenderProductDocumentRequirement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LenderProductDocumentRequirement');
    }

    public function restore(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('Restore:LenderProductDocumentRequirement');
    }

    public function forceDelete(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('ForceDelete:LenderProductDocumentRequirement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LenderProductDocumentRequirement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LenderProductDocumentRequirement');
    }

    public function replicate(AuthUser $authUser, LenderProductDocumentRequirement $lenderProductDocumentRequirement): bool
    {
        return $authUser->can('Replicate:LenderProductDocumentRequirement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LenderProductDocumentRequirement');
    }
}
