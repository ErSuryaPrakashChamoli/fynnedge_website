<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VideoTestimonial;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VideoTestimonialPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VideoTestimonial');
    }

    public function view(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('View:VideoTestimonial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VideoTestimonial');
    }

    public function update(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('Update:VideoTestimonial');
    }

    public function delete(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('Delete:VideoTestimonial');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VideoTestimonial');
    }

    public function restore(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('Restore:VideoTestimonial');
    }

    public function forceDelete(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('ForceDelete:VideoTestimonial');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VideoTestimonial');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VideoTestimonial');
    }

    public function replicate(AuthUser $authUser, VideoTestimonial $videoTestimonial): bool
    {
        return $authUser->can('Replicate:VideoTestimonial');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VideoTestimonial');
    }
}
