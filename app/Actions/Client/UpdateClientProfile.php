<?php

namespace App\Actions\Client;

use App\Enums\VerificationStatus;
use App\Models\ClientProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateClientProfile
{
    /**
     * Update the business profile and any uploaded documents.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        ClientProfile $profile,
        array $attributes,
        ?UploadedFile $logo = null,
        ?UploadedFile $permit = null,
    ): ClientProfile {
        return DB::transaction(function () use ($profile, $attributes, $logo, $permit) {
            $changes = collect($attributes)
                ->only([
                    'business_name', 'business_description', 'owner_name',
                    'address', 'city', 'province', 'phone_number',
                    'contact_email', 'website_url', 'facebook_url',
                ])
                ->all();

            if ($logo !== null) {
                $this->deleteExisting($profile->logo_path);
                $changes['logo_path'] = $logo->store('business-logos', 'public');
            }

            if ($permit !== null) {
                $this->deleteExisting($profile->permit_path, 'local');
                $changes['permit_path'] = $permit->store('business-permits', 'local');
                /**
                 * A new permit restarts review — a verified badge must never
                 * outlive the document it was granted for.
                 */
                $changes['verification_status'] = VerificationStatus::Pending;
                $changes['verified_at'] = null;
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
