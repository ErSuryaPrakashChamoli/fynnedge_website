<?php

declare(strict_types=1);

namespace App\Modules\Journey\Policies;

use App\Modules\Journey\Models\JourneySession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class JourneySessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JourneySession');
    }

    public function view(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('View:JourneySession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JourneySession');
    }

    public function update(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('Update:JourneySession');
    }

    public function delete(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('Delete:JourneySession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JourneySession');
    }

    public function restore(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('Restore:JourneySession');
    }

    public function forceDelete(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('ForceDelete:JourneySession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JourneySession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JourneySession');
    }

    public function replicate(AuthUser $authUser, JourneySession $journeySession): bool
    {
        return $authUser->can('Replicate:JourneySession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JourneySession');
    }
}
