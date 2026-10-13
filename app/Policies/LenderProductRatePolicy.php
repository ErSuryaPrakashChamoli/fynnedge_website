<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LenderProductRate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LenderProductRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LenderProductRate');
    }

    public function view(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('View:LenderProductRate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LenderProductRate');
    }

    public function update(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('Update:LenderProductRate');
    }

    public function delete(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('Delete:LenderProductRate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LenderProductRate');
    }

    public function restore(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('Restore:LenderProductRate');
    }

    public function forceDelete(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('ForceDelete:LenderProductRate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LenderProductRate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LenderProductRate');
    }

    public function replicate(AuthUser $authUser, LenderProductRate $lenderProductRate): bool
    {
        return $authUser->can('Replicate:LenderProductRate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LenderProductRate');
    }

    /**
     * Send a draft for approval. The draft's own state is checked by
     * App\Support\Rates\RateWorkflow, not here.
     */
    public function submit(AuthUser $authUser, ?LenderProductRate $lenderProductRate = null): bool
    {
        return $authUser->can('Submit:LenderProductRate');
    }

    /**
     * Approve, publish, send back or withdraw rates. Also checked without a
     * record, for the bulk "Approve & publish selected" action.
     */
    public function publish(AuthUser $authUser, ?LenderProductRate $lenderProductRate = null): bool
    {
        return $authUser->can('Publish:LenderProductRate');
    }
}
