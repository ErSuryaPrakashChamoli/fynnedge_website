<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalculatorPage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CalculatorPagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CalculatorPage');
    }

    public function view(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('View:CalculatorPage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CalculatorPage');
    }

    public function update(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('Update:CalculatorPage');
    }

    public function delete(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('Delete:CalculatorPage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CalculatorPage');
    }

    public function restore(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('Restore:CalculatorPage');
    }

    public function forceDelete(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('ForceDelete:CalculatorPage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CalculatorPage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CalculatorPage');
    }

    public function replicate(AuthUser $authUser, CalculatorPage $calculatorPage): bool
    {
        return $authUser->can('Replicate:CalculatorPage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CalculatorPage');
    }
}
