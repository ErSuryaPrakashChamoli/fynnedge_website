<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoanLandingPage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LoanLandingPagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanLandingPage');
    }

    public function view(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('View:LoanLandingPage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanLandingPage');
    }

    public function update(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('Update:LoanLandingPage');
    }

    public function delete(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('Delete:LoanLandingPage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanLandingPage');
    }

    public function restore(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('Restore:LoanLandingPage');
    }

    public function forceDelete(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('ForceDelete:LoanLandingPage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanLandingPage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanLandingPage');
    }

    public function replicate(AuthUser $authUser, LoanLandingPage $loanLandingPage): bool
    {
        return $authUser->can('Replicate:LoanLandingPage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanLandingPage');
    }
}
