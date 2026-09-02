<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HowItWorksStep;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class HowItWorksStepPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HowItWorksStep');
    }

    public function view(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('View:HowItWorksStep');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HowItWorksStep');
    }

    public function update(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('Update:HowItWorksStep');
    }

    public function delete(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('Delete:HowItWorksStep');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:HowItWorksStep');
    }

    public function restore(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('Restore:HowItWorksStep');
    }

    public function forceDelete(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('ForceDelete:HowItWorksStep');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HowItWorksStep');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HowItWorksStep');
    }

    public function replicate(AuthUser $authUser, HowItWorksStep $howItWorksStep): bool
    {
        return $authUser->can('Replicate:HowItWorksStep');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HowItWorksStep');
    }
}
