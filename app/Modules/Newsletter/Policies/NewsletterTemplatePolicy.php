<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Policies;

use App\Modules\Newsletter\Models\NewsletterTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NewsletterTemplate');
    }

    public function view(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('View:NewsletterTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NewsletterTemplate');
    }

    public function update(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('Update:NewsletterTemplate');
    }

    public function delete(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('Delete:NewsletterTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NewsletterTemplate');
    }

    public function restore(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('Restore:NewsletterTemplate');
    }

    public function forceDelete(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('ForceDelete:NewsletterTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NewsletterTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NewsletterTemplate');
    }

    public function replicate(AuthUser $authUser, NewsletterTemplate $newsletterTemplate): bool
    {
        return $authUser->can('Replicate:NewsletterTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NewsletterTemplate');
    }
}
