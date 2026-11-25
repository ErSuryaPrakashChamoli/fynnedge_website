<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Author;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Authors are part of article editing, so they follow the Article
 * permissions instead of a separate "*:Author" set: whoever may edit
 * articles may manage the authors they credit, and deleting an author needs
 * the article delete permission. This keeps exactly the audience the interim
 * Authors screen had (Update:Article) without new permission rows — the
 * Marketing and SEO roles keep working as they are.
 *
 * Regenerating policies with `shield:generate --all` would overwrite this
 * mapping; regenerate only specific resources.
 */
class AuthorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Article');
    }

    public function view(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Update:Article');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Article');
    }

    public function update(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Update:Article');
    }

    public function delete(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Delete:Article');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Article');
    }
}
