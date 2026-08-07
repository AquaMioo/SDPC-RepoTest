<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\UpdateClientProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateClientProfileRequest;
use App\Models\ClientProfile;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClientProfileController extends Controller
{
    /**
     * Show the business profile form.
     */
    public function edit(Request $request): Response
    {
        $profile = $this->profileFor($request->user()->currentTeam);

        Gate::authorize('view', $profile);

        return Inertia::render('client/profile', [
            'profile' => [
                'businessName' => $profile->business_name,
                'businessDescription' => $profile->business_description,
                'ownerName' => $profile->owner_name,
                'address' => $profile->address,
                'city' => $profile->city,
                'province' => $profile->province,
                'phoneNumber' => $profile->phone_number,
                'contactEmail' => $profile->contact_email,
                'websiteUrl' => $profile->website_url,
                'facebookUrl' => $profile->facebook_url,
                'logoUrl' => $profile->logo_path ? Storage::disk('public')->url($profile->logo_path) : null,
                'hasPermit' => $profile->permit_path !== null,
                'verificationStatus' => $profile->verification_status->value,
                'verificationLabel' => $profile->verification_status->label(),
                'verificationTagVariant' => $profile->verification_status->tagVariant(),
                'verifiedAt' => $profile->verified_at?->toDateString(),
                'completion' => $profile->completionPercentage(),
            ],
            'canUpdate' => Gate::allows('update', $profile),
        ]);
    }

    /**
     * Update the business profile.
     */
    public function update(
        UpdateClientProfileRequest $request,
        UpdateClientProfile $updateClientProfile,
    ): RedirectResponse {
        $profile = $this->profileFor($request->user()->currentTeam);

        $updateClientProfile->handle(
            $profile,
            $request->validated(),
            $request->file('logo'),
            $request->file('permit'),
        );

        return back()->with('success', 'Business profile updated.');
    }

    /**
     * Get the team's business profile, creating a shell on first visit.
     *
     * A client always has exactly one profile; seeding it lazily here avoids
     * every read path having to null-check it.
     */
    protected function profileFor(Team $team): ClientProfile
    {
        return $team->clientProfile ?? $team->clientProfile()->create([
            'business_name' => $team->name,
        ]);
    }
}
