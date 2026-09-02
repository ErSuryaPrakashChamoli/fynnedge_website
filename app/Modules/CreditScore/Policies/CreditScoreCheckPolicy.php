<?php

declare(strict_types=1);

namespace App\Modules\CreditScore\Policies;

use App\Modules\CreditScore\Models\CreditScoreCheck;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CreditScoreCheckPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CreditScoreCheck');
    }

    public function view(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('View:CreditScoreCheck');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CreditScoreCheck');
    }

    public function update(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('Update:CreditScoreCheck');
    }

    public function delete(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('Delete:CreditScoreCheck');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CreditScoreCheck');
    }

    public function restore(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('Restore:CreditScoreCheck');
    }

    public function forceDelete(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('ForceDelete:CreditScoreCheck');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CreditScoreCheck');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CreditScoreCheck');
    }

    public function replicate(AuthUser $authUser, CreditScoreCheck $creditScoreCheck): bool
    {
        return $authUser->can('Replicate:CreditScoreCheck');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CreditScoreCheck');
    }
}
