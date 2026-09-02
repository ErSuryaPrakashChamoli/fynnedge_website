<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MarketingSection;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MarketingSectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MarketingSection');
    }

    public function view(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('View:MarketingSection');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MarketingSection');
    }

    public function update(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('Update:MarketingSection');
    }

    public function delete(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('Delete:MarketingSection');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MarketingSection');
    }

    public function restore(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('Restore:MarketingSection');
    }

    public function forceDelete(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('ForceDelete:MarketingSection');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MarketingSection');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MarketingSection');
    }

    public function replicate(AuthUser $authUser, MarketingSection $marketingSection): bool
    {
        return $authUser->can('Replicate:MarketingSection');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MarketingSection');
    }
}
