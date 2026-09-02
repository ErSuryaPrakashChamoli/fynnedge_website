<?php

declare(strict_types=1);

namespace App\Modules\Eligibility\Policies;

use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EligibilityRuleSetPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EligibilityRuleSet');
    }

    public function view(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('View:EligibilityRuleSet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EligibilityRuleSet');
    }

    public function update(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('Update:EligibilityRuleSet');
    }

    public function delete(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('Delete:EligibilityRuleSet');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EligibilityRuleSet');
    }

    public function restore(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('Restore:EligibilityRuleSet');
    }

    public function forceDelete(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('ForceDelete:EligibilityRuleSet');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EligibilityRuleSet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EligibilityRuleSet');
    }

    public function replicate(AuthUser $authUser, EligibilityRuleSet $eligibilityRuleSet): bool
    {
        return $authUser->can('Replicate:EligibilityRuleSet');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EligibilityRuleSet');
    }
}
