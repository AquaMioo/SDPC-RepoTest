<?php

namespace App\Actions\Client;

use App\Models\ClientProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateClientProfile
{
    /**
     * Update the business profile and its logo.
     *
     * Nothing here touches verification_status any more. It used to: uploading
     * a permit reset the profile to Pending, on the reasoning that a verified
     * badge must not outlive the document it was granted for. That was right
     * while administrators reviewed permits. They no longer do — a business is
     * verified once, at registration (App\Actions\Fortify\CreateNewUser), and
     * nothing anywhere can grant it a second time. So the reset was a one-way
     * door: a client who uploaded their own permit lost the ability to post
     * work, hire, invite or publish a testimonial, permanently, and dropped
     * off the student-facing client list.
     *
     * The permit upload is gone from the profile screen with it. permit_path
     * and any file already stored are left alone, for whenever review returns.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        ClientProfile $profile,
        array $attributes,
        ?UploadedFile $logo = null,
    ): ClientProfile {
        return DB::transaction(function () use ($profile, $attributes, $logo) {
            $changes = collect($attributes)
                ->only([
                    'business_name', 'business_description', 'owner_name',
                    'industry', 'organization_size', 'tagline',
                    'address', 'city', 'province', 'phone_number',
                    'contact_email', 'website_url', 'facebook_url',
                ])
                ->all();

            if ($logo !== null) {
                $this->deleteExisting($profile->logo_path);
                $changes['logo_path'] = $logo->store('business-logos', 'public');
            }

            $profile->update($changes);

            return $profile->refresh();
        });
    }

    /**
     * Remove a previously stored file, if one exists.
     */
    protected function deleteExisting(?string $path, string $disk = 'public'): void
    {
        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
