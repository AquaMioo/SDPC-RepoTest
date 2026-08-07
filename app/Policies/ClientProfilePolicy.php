<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\ClientProfile;
use App\Models\User;

class ClientProfilePolicy
{
    /**
     * Determine whether the user can view the business profile.
     */
    public function view(User $user, ClientProfile $profile): bool
    {
        return $user->belongsToTeam($profile->team);
    }

    /**
     * Determine whether the user can update the business profile.
     */
    public function update(User $user, ClientProfile $profile): bool
    {
        return $user->belongsToTeam($profile->team)
            && $user->hasTeamPermission($profile->team, TeamPermission::UpdateClientProfile);
    }

    /**
     * Determine whether the user can submit the business permit for review.
     *
     * Verification is an administrator decision; the client may only submit,
     * and only when a review is not already in flight or settled.
     */
    public function submitForVerification(User $user, ClientProfile $profile): bool
    {
        return $this->update($user, $profile) && ! $profile->isVerified();
    }
}
