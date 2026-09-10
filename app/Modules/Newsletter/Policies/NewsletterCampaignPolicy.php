<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Policies;

use App\Modules\Newsletter\Models\NewsletterCampaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsletterCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NewsletterCampaign');
    }

    public function view(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('View:NewsletterCampaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NewsletterCampaign');
    }

    public function update(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('Update:NewsletterCampaign');
    }

    public function delete(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('Delete:NewsletterCampaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NewsletterCampaign');
    }

    public function restore(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('Restore:NewsletterCampaign');
    }

    public function forceDelete(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('ForceDelete:NewsletterCampaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NewsletterCampaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NewsletterCampaign');
    }

    public function replicate(AuthUser $authUser, NewsletterCampaign $newsletterCampaign): bool
    {
        return $authUser->can('Replicate:NewsletterCampaign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NewsletterCampaign');
    }
}
