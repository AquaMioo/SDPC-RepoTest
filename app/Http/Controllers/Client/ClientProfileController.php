<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\UpdateClientProfile;
use App\Enums\Industry;
use App\Enums\OrganizationSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateClientProfileRequest;
use App\Models\ClientProfile;
use App\Models\Location;
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
                'industry' => $profile->industry?->value,
                'industryLabel' => $profile->industry?->label(),
                'organizationSize' => $profile->organization_size?->value,
                'organizationSizeLabel' => $profile->organization_size?->label(),
                'tagline' => $profile->tagline,
                'ownerName' => $profile->owner_name,
                'address' => $profile->address,
                'city' => $profile->city,
                'province' => $profile->province,
                'phoneNumber' => $profile->phone_number,
                'contactEmail' => $profile->contact_email,
                'websiteUrl' => $profile->website_url,
                'facebookUrl' => $profile->facebook_url,
                'logoUrl' => $profile->logo_path ? Storage::disk('public')->url($profile->logo_path) : null,
                'verificationStatus' => $profile->verification_status->value,
                'verificationLabel' => $profile->verification_status->label(),
                'verificationTagVariant' => $profile->verification_status->tagVariant(),
                'verifiedAt' => $profile->verified_at?->toDateString(),
                'completion' => $profile->completionPercentage(),
            ],
            /*
             * The account behind the business. This is the only screen a
             * client can edit their own name, address and picture from - the
             * settings page carries no editor any more - so it is served here
             * rather than read off the shared auth prop, which does not carry
             * the raw email.
             */
            'account' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatarUrl' => $request->user()->avatarUrl(),
                'roleLabel' => $request->user()->role->label(),
            ],
            'industries' => Industry::options(),
            'organizationSizes' => OrganizationSize::options(),
            'testimonial' => $this->testimonialFor($request->user()->currentTeam),
            /*
             * Writing one is gated on verification the same way posting work
             * is, so the screen can say why the box is disabled rather than
             * bouncing the client off a middleware redirect.
             */
            'canPublishTestimonial' => $profile->isVerified(),
            'canUpdate' => Gate::allows('update', $profile),
            /*
             * The province and city selects. Sent whole rather than queried per
             * keystroke: it is one province's worth of rows, so the city select
             * can filter in the browser without a round trip.
             */
            'locations' => Location::groupedByProvince(),
        ]);
    }

    /**
     * Shape the team's testimonial for the form, if it has written one.
     *
     * @return array{body: string, authorTitle: string|null, updatedAt: string|null}|null
     */
    protected function testimonialFor(Team $team): ?array
    {
        $testimonial = $team->testimonial;

        if ($testimonial === null) {
            return null;
        }

        return [
            'body' => $testimonial->body,
            'authorTitle' => $testimonial->author_title,
            'updatedAt' => $testimonial->updated_at?->toDateString(),
        ];
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
