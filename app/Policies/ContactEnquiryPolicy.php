<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactEnquiry;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContactEnquiryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContactEnquiry');
    }

    public function view(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('View:ContactEnquiry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContactEnquiry');
    }

    public function update(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('Update:ContactEnquiry');
    }

    public function delete(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('Delete:ContactEnquiry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContactEnquiry');
    }

    public function restore(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('Restore:ContactEnquiry');
    }

    public function forceDelete(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('ForceDelete:ContactEnquiry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ContactEnquiry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ContactEnquiry');
    }

    public function replicate(AuthUser $authUser, ContactEnquiry $contactEnquiry): bool
    {
        return $authUser->can('Replicate:ContactEnquiry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ContactEnquiry');
    }
}
