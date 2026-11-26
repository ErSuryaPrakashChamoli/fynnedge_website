<?php

namespace App\Modules\DebtConsolidation\Policies;

use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtForeclosureQuotationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtForeclosureQuotation');
    }

    public function view(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('View:DebtForeclosureQuotation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DebtForeclosureQuotation');
    }

    public function update(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('Update:DebtForeclosureQuotation');
    }

    public function delete(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('Delete:DebtForeclosureQuotation');
    }

    public function restore(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('Restore:DebtForeclosureQuotation');
    }

    public function forceDelete(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('ForceDelete:DebtForeclosureQuotation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DebtForeclosureQuotation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DebtForeclosureQuotation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DebtForeclosureQuotation');
    }

    public function replicate(AuthUser $authUser, DebtForeclosureQuotation $debtForeclosureQuotation): bool
    {
        return $authUser->can('Replicate:DebtForeclosureQuotation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DebtForeclosureQuotation');
    }
}
