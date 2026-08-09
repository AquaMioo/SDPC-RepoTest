<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewBusinessRequest;
use App\Models\ClientProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review of business permits, the client-side twin of the student credential
 * queue. A client cannot post work or hire anyone until a permit lands here
 * and an administrator accepts it.
 */
class AdminBusinessController extends Controller
{
    /**
     * Show every business that has submitted a permit, waiting ones first.
     */
    public function index(): Response
    {
        $businesses = ClientProfile::query()
            ->with('team')
            ->whereNotNull('permit_path')
            ->latest('id')
            ->get()
            ->sortBy(fn (ClientProfile $profile): int => $profile->verification_status === VerificationStatus::Pending ? 0 : 1)
            ->values()
            ->map(fn (ClientProfile $profile): array => [
                'id' => $profile->id,
                'businessName' => $profile->business_name,
                'ownerName' => $profile->owner_name,
                'contactEmail' => $profile->contact_email,
                'teamName' => $profile->team?->name,
                'city' => $profile->city,
                'province' => $profile->province,
                'completion' => $profile->completionPercentage(),
                'status' => $profile->verification_status->value,
                'statusLabel' => $profile->verification_status->label(),
                'statusTagVariant' => $profile->verification_status->tagVariant(),
                'verifiedAt' => $profile->verified_at?->diffForHumans(),
                'awaitingDecision' => $profile->verification_status === VerificationStatus::Pending,
            ])
            ->all();

        return Inertia::render('admin/businesses', [
            'businesses' => $businesses,
        ]);
    }

    /**
     * Stream the stored permit to the reviewing administrator.
     *
     * Permits sit on a private disk and are never linked directly. This route
     * behind auth + role:admin is the only way to read one.
     */
    public function permit(ClientProfile $business): StreamedResponse
    {
        abort_if($business->permit_path === null, HttpResponse::HTTP_NOT_FOUND);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($business->permit_path), HttpResponse::HTTP_NOT_FOUND);

        return $disk->download($business->permit_path, $business->business_name.' permit');
    }

    /**
     * Record the administrator's decision.
     */
    public function update(ReviewBusinessRequest $request, ClientProfile $business): RedirectResponse
    {
        if ($business->permit_path === null) {
            return back()->withErrors([
                'decision' => __('This business has not submitted a permit yet.'),
            ]);
        }

        $decision = $request->decision();

        $business->forceFill([
            'verification_status' => $decision,
            'verified_at' => $decision === VerificationStatus::Verified ? now() : null,
        ])->save();

        Inertia::flash('toast', [
            'type' => $decision === VerificationStatus::Verified ? 'success' : 'error',
            'message' => $decision === VerificationStatus::Verified
                ? __(':name is verified and can now post work.', ['name' => $business->business_name])
                : __(':name was rejected. They can upload a new permit.', ['name' => $business->business_name]),
        ]);

        return back();
    }
}
