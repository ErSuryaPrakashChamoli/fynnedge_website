<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SchemaTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SchemaTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SchemaTemplate');
    }

    public function view(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('View:SchemaTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SchemaTemplate');
    }

    public function update(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('Update:SchemaTemplate');
    }

    public function delete(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('Delete:SchemaTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SchemaTemplate');
    }

    public function restore(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('Restore:SchemaTemplate');
    }

    public function forceDelete(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('ForceDelete:SchemaTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SchemaTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SchemaTemplate');
    }

    public function replicate(AuthUser $authUser, SchemaTemplate $schemaTemplate): bool
    {
        return $authUser->can('Replicate:SchemaTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SchemaTemplate');
    }
}
