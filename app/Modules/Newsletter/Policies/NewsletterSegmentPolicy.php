<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Policies;

use App\Modules\Newsletter\Models\NewsletterSegment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterSegmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NewsletterSegment');
    }

    public function view(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('View:NewsletterSegment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NewsletterSegment');
    }

    public function update(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('Update:NewsletterSegment');
    }

    public function delete(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('Delete:NewsletterSegment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NewsletterSegment');
    }

    public function restore(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('Restore:NewsletterSegment');
    }

    public function forceDelete(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('ForceDelete:NewsletterSegment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NewsletterSegment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NewsletterSegment');
    }

    public function replicate(AuthUser $authUser, NewsletterSegment $newsletterSegment): bool
    {
        return $authUser->can('Replicate:NewsletterSegment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NewsletterSegment');
    }
}
