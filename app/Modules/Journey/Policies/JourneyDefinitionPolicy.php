<?php

declare(strict_types=1);

namespace App\Modules\Journey\Policies;

use App\Modules\Journey\Models\JourneyDefinition;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class JourneyDefinitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JourneyDefinition');
    }

    public function view(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('View:JourneyDefinition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JourneyDefinition');
    }

    public function update(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('Update:JourneyDefinition');
    }

    public function delete(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('Delete:JourneyDefinition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JourneyDefinition');
    }

    public function restore(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('Restore:JourneyDefinition');
    }

    public function forceDelete(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('ForceDelete:JourneyDefinition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JourneyDefinition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JourneyDefinition');
    }

    public function replicate(AuthUser $authUser, JourneyDefinition $journeyDefinition): bool
    {
        return $authUser->can('Replicate:JourneyDefinition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JourneyDefinition');
    }
}
