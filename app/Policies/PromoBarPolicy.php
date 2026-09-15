<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromoBar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PromoBarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PromoBar');
    }

    public function view(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('View:PromoBar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PromoBar');
    }

    public function update(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('Update:PromoBar');
    }

    public function delete(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('Delete:PromoBar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PromoBar');
    }

    public function restore(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('Restore:PromoBar');
    }

    public function forceDelete(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('ForceDelete:PromoBar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PromoBar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PromoBar');
    }

    public function replicate(AuthUser $authUser, PromoBar $promoBar): bool
    {
        return $authUser->can('Replicate:PromoBar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PromoBar');
    }
}
