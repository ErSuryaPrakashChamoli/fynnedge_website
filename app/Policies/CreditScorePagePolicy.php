<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditScorePage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CreditScorePagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CreditScorePage');
    }

    public function view(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('View:CreditScorePage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CreditScorePage');
    }

    public function update(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('Update:CreditScorePage');
    }

    public function delete(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('Delete:CreditScorePage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CreditScorePage');
    }

    public function restore(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('Restore:CreditScorePage');
    }

    public function forceDelete(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('ForceDelete:CreditScorePage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CreditScorePage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CreditScorePage');
    }

    public function replicate(AuthUser $authUser, CreditScorePage $creditScorePage): bool
    {
        return $authUser->can('Replicate:CreditScorePage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CreditScorePage');
    }
}
