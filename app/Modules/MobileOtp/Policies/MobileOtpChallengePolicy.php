<?php

declare(strict_types=1);

namespace App\Modules\MobileOtp\Policies;

use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Viewing is a Shield permission like any other resource. Everything else is
 * refused outright rather than permission-gated: a challenge is evidence of
 * what a visitor proved, and no role should be able to create one, alter its
 * status or erase it. (super_admin bypasses policies via Gate::before, which is
 * why MobileOtpChallengeResource also returns false from canCreate/canEdit/canDelete.)
 */
class MobileOtpChallengePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MobileOtpChallenge');
    }

    public function view(AuthUser $authUser, MobileOtpChallenge $mobileOtpChallenge): bool
    {
        return $authUser->can('View:MobileOtpChallenge');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, MobileOtpChallenge $mobileOtpChallenge): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, MobileOtpChallenge $mobileOtpChallenge): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
